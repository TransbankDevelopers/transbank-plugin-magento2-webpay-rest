<?php

namespace Transbank\Webpay\Controller\Transaction;

use Throwable;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Transbank\Webpay\Exceptions\EcommerceException;
use Transbank\Webpay\Helper\ObjectManagerHelper;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Helper\TbkResponseHelper;
use Transbank\Webpay\Infrastructure\Lock\MySqlNamedLock;
use Transbank\Webpay\Model\Service\QuoteService;
use Transbank\Webpay\Model\Service\WebpayOrderDataService;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;

/**
 * Controller for commit transaction Webpay.
 */
class CommitWebpay extends \Magento\Framework\App\Action\Action
{
    const WEBPAY_NORMAL_FLOW = 'normal';
    const WEBPAY_TIMEOUT_FLOW = 'timeout';
    const WEBPAY_ABORTED_FLOW = 'aborted';
    const WEBPAY_ERROR_FLOW = 'error';
    const WEBPAY_INVALID_FLOW = 'invalid';

    const WEBPAY_FAILED_FLOW_MESSAGE = 'Tu transacción no pudo ser autorizada. Ningún cobro fue realizado.';
    const WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE = 'Orden cancelada por el usuario.';
    const WEBPAY_TIMEOUT_FLOW_MESSAGE = 'Orden cancelada por inactividad del usuario en el formulario de pago.';
    const WEBPAY_ERROR_FLOW_MESSAGE = 'Orden cancelada por un error en el formulario de pago';
    const WEBPAY_EXCEPTION_FLOW_MESSAGE = 'No se pudo procesar el pago.';

    protected $configProvider;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $resultRawFactory;
    protected $resultPageFactory;
    protected $eventManager;
    protected $webpayOrderDataService;
    protected $log;
    protected $messageManager;
    private $quoteService;
    private MySqlNamedLock $webpayReturnLock;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        WebpayOrderDataService $webpayOrderDataService,
        QuoteService $quoteService,
        MySqlNamedLock $webpayReturnLock
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->eventManager = $eventManager;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->webpayOrderDataService = $webpayOrderDataService;
        $this->log = new PluginLogger();
        $this->quoteService = $quoteService;
        $this->webpayReturnLock = $webpayReturnLock;
    }

    /**
     * @Override
     */
    public function execute()
    {
        try {
            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $request = $requestMethod === 'POST' ? $_POST : $_GET;

            $this->log->logInfo('Procesando retorno desde formulario de Webpay.', [
                'method' => $requestMethod,
                'token_ws' => $request['token_ws'] ?? null,
                'TBK_TOKEN' => $request['TBK_TOKEN'] ?? null,
                'TBK_ID_SESION' => $request['TBK_ID_SESION'] ?? null,
                'TBK_ORDEN_COMPRA' => $request['TBK_ORDEN_COMPRA'] ?? null,
            ]);

            return $this->handleRequest($request);
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    protected function toRedirect($url, $data)
    {
        $response = $this->resultRawFactory->create();
        $content = "<form action='$url' method='POST' name='webpayForm'>";
        foreach ($data as $name => $value) {
            $content .= "<input type='hidden' name='" . htmlentities($name) . "' value='" . htmlentities($value) . "'>";
        }
        $content .= '</form>';
        $content .= "<script language='JavaScript'>document.webpayForm.submit();</script>";
        $response->setContents($content);

        return $response;
    }

    private function handleRequest(array $request)
    {
        $webpayFlow = $this->getWebpayFlow($request);

        if ($webpayFlow == self::WEBPAY_INVALID_FLOW) {
            throw new EcommerceException('Flujo de pago no reconocido.');
        }

        if ($webpayFlow == self::WEBPAY_NORMAL_FLOW) {
            return $this->handleNormalFlow($request['token_ws']);
        }

        if ($webpayFlow == self::WEBPAY_TIMEOUT_FLOW) {
            return $this->handleFlowTimeout($request['TBK_ORDEN_COMPRA']);
        }

        if ($webpayFlow == self::WEBPAY_ABORTED_FLOW) {
            return $this->handleFlowAborted($request['TBK_TOKEN']);
        }

        if ($webpayFlow == self::WEBPAY_ERROR_FLOW) {
            return $this->handleFlowError($request['token_ws']);
        }
    }

    private function getWebpayFlow(array $request): string
    {
        $tokenWs = $request['token_ws'] ?? null;
        $tbkToken = $request['TBK_TOKEN'] ?? null;
        $tbkIdSession = $request['TBK_ID_SESION'] ?? null;
        $webpayFlow = self::WEBPAY_INVALID_FLOW;

        if (isset($tokenWs) && isset($tbkToken)) {
            $webpayFlow = self::WEBPAY_ERROR_FLOW;
        }

        if (isset($tbkIdSession) && isset($tbkToken) && !isset($tokenWs)) {
            $webpayFlow = self::WEBPAY_ABORTED_FLOW;
        }

        if (isset($tbkIdSession) && !isset($tbkToken) && !isset($tokenWs)) {
            $webpayFlow = self::WEBPAY_TIMEOUT_FLOW;
        }

        if (isset($tokenWs) && !isset($tbkToken) && !isset($tbkIdSession)) {
            $webpayFlow = self::WEBPAY_NORMAL_FLOW;
        }

        return $webpayFlow;
    }

    private function handleNormalFlow(string $token)
    {
        $lockAcquired = false;
        $this->log->logInfo('Procesando transacción por flujo Normal', ['token' => $token]);

        try {
            $lockAcquired = $this->acquireWebpayReturnLockWithRetries($token);

            if (!$lockAcquired) {
                throw new EcommerceException('No se pudo adquirir el lock de retorno de Webpay.');
            }

            if ($this->checkTransactionIsAlreadyProcessed($token)) {
                return $this->handleTransactionAlreadyProcessed($token);
            }

            return $this->processTransaction($token);
        } finally {
            $this->releaseWebpayReturnLock($token, $lockAcquired);
        }
    }

    private function processTransaction(string $token)
    {
        $config = $this->configProvider->getPluginConfig();
        $webpayOrderData = $this->webpayOrderDataService->getByToken($token);
        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
        $commitResponse = $transbankSdkWebpay->commitTransaction($token);

        if (is_array($commitResponse) && isset($commitResponse['error'])) {
            return $this->handleFlowError($token);
        }

        if ($commitResponse->isApproved()) {
            return $this->handleAuthorizedTransaction($order, $webpayOrderData, $commitResponse);
        }

        return $this->handleUnauthorizedTransaction($order, $webpayOrderData, $commitResponse);
    }

    /**
     * Tries to acquire the return lock with internal retries.
     *
     * @param string $token The transaction token.
     * @return bool True when the lock is acquired, false when all retries are exhausted.
     */
    private function acquireWebpayReturnLockWithRetries(string $token): bool
    {
        $maxAttempts = 4;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                if ($this->webpayReturnLock->acquire($token)) {
                    return true;
                }

                $this->log->logInfo("Lock de retorno ocupado, intento {$attempt}/{$maxAttempts} => token: {$token}");
            } catch (\Throwable $e) {
                $this->log->logError("Error al adquirir lock de retorno, intento {$attempt}/{$maxAttempts} => token: {$token} - Error: {$e->getMessage()}");
            }
        }

        return false;
    }

    /**
     * Releases the return lock only when it was actually acquired.
     *
     * @param string $token
     * @param bool $lockAcquired
     * @return void
     */
    private function releaseWebpayReturnLock(string $token, bool $lockAcquired): void
    {
        if (!$lockAcquired) {
            return;
        }

        try {
            $released = $this->webpayReturnLock->release($token);

            if (!$released) {
                $this->log->logWarning("No se pudo liberar el lock de retorno de Webpay token => {$token}");
            }
        } catch (\Throwable $e) {
            $this->log->logError("Error al liberar el lock de retorno de Webpay token => {$token} - Error: {$e->getMessage()}");
        }
    }

    private function handleFlowTimeout(string $buyOrder)
    {
        $this->log->logInfo('Procesando transacción por flujo timeout', ['buyOrder' => $buyOrder]);

        $message = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;

        $webpayOrderData = $this->webpayOrderDataService->getByBuyOrder($buyOrder);
        $token = $webpayOrderData->getToken();

        if ($this->checkTransactionIsAlreadyProcessed($token)) {
            return $this->handleTransactionAlreadyProcessed($token);
        }

        return $this->handleAbortedTransaction($token, $message, WebpayOrderData::PAYMENT_STATUS_TIMEOUT);
    }

    private function handleFlowAborted(string $token)
    {
        $this->log->logInfo('Procesando transacción por flujo de pago abortado', ['token' => $token]);

        if ($this->checkTransactionIsAlreadyProcessed($token)) {
            return $this->handleTransactionAlreadyProcessed($token);
        }

        $message = self::WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE;

        return $this->handleAbortedTransaction($token, $message, WebpayOrderData::PAYMENT_STATUS_CANCELED_BY_USER);
    }

    private function handleFlowError(string $token)
    {
        $this->log->logInfo('Procesando transacción por flujo de error en formulario de pago', ['token' => $token]);

        if ($this->checkTransactionIsAlreadyProcessed($token)) {
            return $this->handleTransactionAlreadyProcessed($token);
        }

        $message = self::WEBPAY_ERROR_FLOW_MESSAGE;

        return $this->handleAbortedTransaction($token, $message, WebpayOrderData::PAYMENT_STATUS_ERROR);
    }

    private function handleAuthorizedTransaction(
        Order $order,
        WebpayOrderData $webpayOrderData,
        TransactionCommitResponse $commitResponse
    ) {

        $token = $webpayOrderData->getToken();
        $this->log->logInfo('Transacción autorizada por Transbank, procesando orden => Token: ' . $token);

        $this->webpayOrderDataService->updateMetadataAndPaymentStatus(
            $webpayOrderData,
            json_encode($commitResponse),
            WebpayOrderData::PAYMENT_STATUS_SUCCESS
        );

        $authorizationCode = $commitResponse->getAuthorizationCode();
        $payment = $order->getPayment();
        $payment->setLastTransId($authorizationCode);
        $payment->setTransactionId($authorizationCode);
        $payment->setAdditionalInformation([
            Transaction::RAW_DETAILS => (array) $commitResponse
        ]);

        $orderStatusSuccess = $this->configProvider->getOrderSuccessStatus();
        $order->setState($orderStatusSuccess)->setStatus($orderStatusSuccess);
        $commitHistoryComment = $this->createCommitHistoryComment($commitResponse);
        $order->addStatusToHistory($order->getStatus(), $commitHistoryComment);
        $order->save();

        $this->log->logInfo('Orden aprobada => Token: ' . $token);

        $this->eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order' => $order]
        );

        $responseData = TbkResponseHelper::getWebpayFormattedResponse($commitResponse);

        return $this->redirectToSuccess($responseData);
    }

    private function handleUnauthorizedTransaction(
        Order $order,
        WebpayOrderData $webpayOrderData,
        TransactionCommitResponse $commitResponse
    ) {
        $token = $webpayOrderData->getToken();
        $this->log->logInfo('Transacción rechazada por Transbank, cancelando orden => token: ' . $token);

        $message = self::WEBPAY_FAILED_FLOW_MESSAGE;

        $this->webpayOrderDataService->updateMetadataAndPaymentStatus(
            $webpayOrderData,
            json_encode($commitResponse),
            WebpayOrderData::PAYMENT_STATUS_FAILED
        );

        $commitHistoryComment = $this->createCommitHistoryComment($commitResponse);
        $this->cancelOrder($order, $commitHistoryComment);
        $this->log->logInfo('Orden cancelada => Token: ' . $token);

        $this->quoteService->reactivateAfterOrderCancelByQuoteId($order->getQuoteId());

        return $this->redirectWithErrorMessage($message);
    }

    private function handleAbortedTransaction(string $token, string $message, string $webpayStatus)
    {
        $this->log->logInfo('Error al procesar transacción por Transbank => token: ' . $token);
        $this->log->logInfo('Detalle: ' . $message);

        $webpayOrderData = $this->webpayOrderDataService->getByToken($token);
        $this->webpayOrderDataService->updatePaymentStatus($webpayOrderData, $webpayStatus);
        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        if ($order != null) {
            $this->cancelOrder($order, $message);
            $this->log->logInfo('Orden cancelada => Token: ' . $token);

            $this->quoteService->reactivateAfterOrderCancelByQuoteId($order->getQuoteId());
        }

        return $this->redirectWithErrorMessage($message);
    }

    private function handleException(Throwable $exception)
    {
        $message = self::WEBPAY_EXCEPTION_FLOW_MESSAGE;

        $this->log->logError('Error al procesar el pago: ');
        $this->log->logError($exception->getMessage());
        $this->log->logError($exception->getTraceAsString());

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order->getId()) {
            $this->quoteService->reactivateAfterOrderCancelByQuoteId($order->getQuoteId());
        }

        return $this->redirectWithErrorMessage($message);
    }

    private function handleTransactionAlreadyProcessed(string $token)
    {
        $this->log->logInfo('Transacción ya se encontraba procesada.');

        $webpayOrderData = $this->webpayOrderDataService->getByToken($token);
        $status = $webpayOrderData->getPaymentStatus();
        $message = self::WEBPAY_EXCEPTION_FLOW_MESSAGE;

        $this->log->logInfo('Estado de la transacción => ' . $status);

        if ($status == WebpayOrderData::PAYMENT_STATUS_SUCCESS) {
            $metadata = $webpayOrderData->getMetadata();
            $response = json_decode($metadata);
            $formattedResponse = TbkResponseHelper::getWebpayFormattedResponse($response);

            return $this->redirectToSuccess($formattedResponse);
        }

        if ($status == WebpayOrderData::PAYMENT_STATUS_FAILED) {
            $message = self::WEBPAY_FAILED_FLOW_MESSAGE;
        }

        if ($status == WebpayOrderData::PAYMENT_STATUS_CANCELED_BY_USER) {
            $message = self::WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE;
        }

        if ($status == WebpayOrderData::PAYMENT_STATUS_TIMEOUT) {
            $message = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
        }

        if ($status == WebpayOrderData::PAYMENT_STATUS_ERROR) {
            $message = self::WEBPAY_ERROR_FLOW_MESSAGE;
        }

        return $this->redirectWithErrorMessage($message);
    }

    private function redirectToSuccess(array $responseData)
    {
        $this->quoteService->deactivate($this->checkoutSession->getQuote());

        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('transbank_checkout_success');
        $block = $resultPage->getLayout()->getBlock('transbank_success');
        $block->setResponse($responseData);
        return $resultPage;
    }

    private function redirectWithErrorMessage(string $message)
    {
        $this->messageManager->addErrorMessage(__($message));
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    private function cancelOrder(Order $order, string $message)
    {
        $orderStatusCanceled = $this->configProvider->getOrderErrorStatus();
        $order->cancel();
        $order->setStatus($orderStatusCanceled);
        $order->addStatusToHistory($order->getStatus(), $message);
        $order->save();
    }

    private function checkTransactionIsAlreadyProcessed($token): bool
    {
        $webpayOrderData = $this->webpayOrderDataService->getByToken($token);
        $status = $webpayOrderData->getPaymentStatus();

        return $status != WebpayOrderData::PAYMENT_STATUS_WATING;
    }

    protected function getOrder($orderId): Order
    {
        $order = ObjectManagerHelper::get(Order::class);
        return $order->load($orderId);
    }

    private function createCommitHistoryComment($commitResponse): string
    {
        if ($commitResponse instanceof TransactionCommitResponse) {
            $transactionLocalDate = TbkResponseHelper::utcToLocalDate($commitResponse->getTransactionDate());
            $commitStatus = $commitResponse->getResponseCode() == 0 ? 'Aprobada' : 'Rechazada';
            $installmentsAmount = $commitResponse->getInstallmentsAmount();
            $balance = $commitResponse->getBalance();
            $historyComment = '<strong>Transacción ' . $commitStatus . '</strong><br><br>' .
                '<strong>VCI</strong>: ' . $commitResponse->getVci() . '<br>' .
                '<strong>Estado</strong>: ' . $commitResponse->getStatus() . '<br>' .
                '<strong>Código de respuesta</strong>: ' . $commitResponse->getResponseCode() . '<br>' .
                '<strong>Monto</strong>: ' . $commitResponse->getAmount() . '<br>' .
                '<strong>Código de autorización</strong>: ' . $commitResponse->getAuthorizationCode() . '<br>' .
                '<strong>Tipo de pago</strong>: ' . $commitResponse->getPaymentTypeCode() . '<br>' .
                '<strong>Cuotas</strong>: ' . $commitResponse->getInstallmentsNumber() . '<br>';
            if ($installmentsAmount != null) {
                $historyComment .= '<strong>Monto cuotas</strong>: ' . $installmentsAmount . '<br>';
            }
            $historyComment .= '<strong>ID de sesión</strong>: ' . $commitResponse->getSessionId() . '<br>' .
                '<strong>Orden de compra</strong>: ' . $commitResponse->getBuyOrder() . '<br>' .
                '<strong>Número de tarjeta</strong>: ' . $commitResponse->getCardNumber() . '<br>' .
                '<strong>Fecha de transacción</strong>: ' . $transactionLocalDate . '<br>';
            if ($balance != null) {
                $historyComment .= '<strong>Saldo</strong>: ' . $balance . '<br>';
            }

            return $historyComment;
        }

        $message = '<strong>Transacción fallida con Webpay</strong>';
        if (isset($commitResponse['error'])) {
            $detail = isset($commitResponse['detail']) ? $commitResponse['detail'] : 'Sin detalles';
            $message .= '<br><br>' .
                '<strong>Respuesta</strong>: ' . $commitResponse['error'] . '<br>' .
                '<strong>Mensaje</strong>: ' . $detail . '<br>';
        }
        return $message;
    }
}
