<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Transbank\Webpay\Helper\ObjectManagerHelper;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Helper\TbkResponseHelper;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;

/**
 * Controller for commit transaction Webpay.
 */
class CommitWebpay extends \Magento\Framework\App\Action\Action
{
    const WEBPAY_NORMAL_FLOW = 'normal';
    const WEBPAY_FLOW_TIMEOUT = 'timeout';
    const WEBPAY_FLOW_ABORTED = 'aborted';
    const WEBPAY_FLOW_ERROR = 'error';

    protected $paymentTypeCodearray = [
        'VD' => 'Venta Debito',
        'VN' => 'Venta Normal',
        'VC' => 'Venta en cuotas',
        'SI' => '3 cuotas sin interés',
        'S2' => '2 cuotas sin interés',
        'NC' => 'N cuotas sin interés',
    ];
    protected $configProvider;
    protected $quoteRepository;
    protected $cart;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $resultRawFactory;
    protected $resultPageFactory;
    protected $webpayOrderDataFactory;
    protected $log;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->log = new PluginLogger();
    }

    /**
     * @Override
     */
    public function execute()
    {
        try {
            $request = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

            return $this->handleRequest($request);
        } catch (\Exception $e) {
            return $this->handleException($e->getMessage());
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

        if ($webpayFlow == self::WEBPAY_NORMAL_FLOW) {
            return $this->handleNormalFlow($request['token_ws']);
        }

        if ($webpayFlow == self::WEBPAY_FLOW_TIMEOUT) {
            return $this->handleFlowTimeout($request['TBK_ORDEN_COMPRA']);
        }

        if ($webpayFlow == self::WEBPAY_FLOW_ABORTED) {
            return $this->handleFlowAborted($request['TBK_TOKEN']);
        }

        if ($webpayFlow == self::WEBPAY_FLOW_ERROR) {
            return $this->handleFlowError($request['token_ws']);
        }
    }

    private function getWebpayFlow(array $request): string
    {
        $tokenWs = $request['token_ws'] ?? null;
        $tbkToken = $request['TBK_TOKEN'] ?? null;
        $tbkIdSession = $request['TBK_ID_SESION'] ?? null;

        if (isset($tokenWs) && isset($tbkToken)) {
            return self::WEBPAY_FLOW_ERROR;
        }

        if (isset($tbkIdSession) && isset($tbkToken) && !isset($tokenWs)) {
            return self::WEBPAY_FLOW_ABORTED;
        }

        if (isset($tbkIdSession) && !isset($tbkToken) && !isset($tokenWs)) {
            return self::WEBPAY_FLOW_TIMEOUT;
        }

        if (isset($tokenWs) && !isset($tbkToken) && !isset($tbkIdSession)) {
            return self::WEBPAY_NORMAL_FLOW;
        }
    }

    private function handleNormalFlow(string $token)
    {
        $config = $this->configProvider->getPluginConfig();
        $webpayOrderData = $this->getWebpayOrderData($token);
        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
        $commitResponse = $transbankSdkWebpay->commitTransaction($token);

        $webpayOrderData->setMetadata(json_encode($commitResponse));

        if ($commitResponse->isApproved()) {
            return $this->handleAuthorizedTransaction($order, $webpayOrderData, $commitResponse);
        }

        return $this->handleUnauthorizedTransaction($order, $webpayOrderData, $commitResponse);
    }

    private function handleFlowTimeout(string $buyOrder)
    {
        $message = 'Orden cancelada por inactividad del usuario en el formulario de pago';

        $this->log->logInfo('Transacción con error => Orden de compra: ' . $buyOrder);
        $this->log->logInfo('Detalle: ' . $message);

        return $this->redirectWithErrorMessage($message);
    }

    private function handleFlowAborted(string $token)
    {
        $message = 'Orden cancelada por el usuario';

        return $this->handleAbortedTransaction($token, $message, WebpayOrderData::PAYMENT_STATUS_CANCELED_BY_USER);
    }

    private function handleFlowError(string $token)
    {
        $message = 'Orden cancelada por un error en el formulario de pago';

        return $this->handleAbortedTransaction($token, $message, WebpayOrderData::PAYMENT_STATUS_ERROR);
    }

    private function handleAuthorizedTransaction(
        Order $order,
        WebpayOrderData $webpayOrderData,
        TransactionCommitResponse $commitResponse
    ) {
        $token = $webpayOrderData->getToken();
        $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_SUCCESS);
        $webpayOrderData->save();

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

        $this->log->logInfo('Transacción aprobada => token: ' . $token);

        $message = $this->getSuccessMessage($this->commitResponseToArray($commitResponse));

        return $this->redirectToSuccess($message);
    }

    private function handleUnauthorizedTransaction(
        Order $order,
        WebpayOrderData $webpayOrderData,
        TransactionCommitResponse $commitResponse
    ) {
        $this->log->logInfo('Transacción rechazada => token: ' . $webpayOrderData->getToken());

        $message = 'Tu transacción no pudo ser autorizada. Ningún cobro fue realizado.';

        $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_FAILED);
        $webpayOrderData->save();

        $commitHistoryComment = $this->createCommitHistoryComment($commitResponse);
        $this->cancelOrder($order, $commitHistoryComment);

        return $this->redirectWithErrorMessage($message);
    }

    private function handleAbortedTransaction(string $token, string $message, string $webpayStatus)
    {
        $this->log->logInfo('Transacción con error => token: ' . $token);
        $this->log->logInfo('Detalle: ' . $message);

        $webpayOrderData = $this->getWebpayOrderData($token);
        $webpayOrderData->setPaymentStatus($webpayStatus);
        $webpayOrderData->save();

        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        if ($order != null) {
            $this->cancelOrder($order, $message);
        }

        return $this->redirectWithErrorMessage($message);
    }

    private function handleException(string $exceptionMessage)
    {
        $message = 'No se pudo procesar el pago ';
        $this->log->logError($message . $exceptionMessage);

        return $this->redirectWithErrorMessage($message);
    }

    private function redirectToSuccess(string $message)
    {
        $this->checkoutSession->getQuote()->setIsActive(false)->save();
        $this->messageManager->addComplexSuccessMessage(
            'successMessage',
            ['message' => $message]
        );
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }

    private function redirectWithErrorMessage(string $message)
    {
        $this->checkoutSession->restoreQuote();
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

    protected function commitResponseToArray($response)
    {
        return [
            'vci'                => $response->getVci(),
            'amount'             => $response->getAmount(),
            'status'             => $response->getStatus(),
            'buyOrder'           => $response->getBuyOrder(),
            'sessionId'          => $response->getSessionId(),
            'cardDetail'         => $response->getCardDetail(),
            'accountingDate'     => $response->getAccountingDate(),
            'transactionDate'    => $response->getTransactionDate(),
            'authorizationCode'  => $response->getAuthorizationCode(),
            'paymentTypeCode'    => $response->getPaymentTypeCode(),
            'responseCode'       => $response->getResponseCode(),
            'installmentsAmount' => $response->getInstallmentsAmount(),
            'installmentsNumber' => $response->getInstallmentsNumber(),
            'balance'            => $response->getBalance(),
        ];
    }

    protected function orderCanceledByUser($token, $quoteId, $orderStatusCanceled)
    {
        $message = 'Orden cancelada por el usuario';

        $webpayOrderData = $this->getWebpayOrderData($token);
        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        $getQuoteById = $this->quoteRepository->get($quoteId);

        if ($getQuoteById) {
            $customerId = $getQuoteById->getCustomerId();
            $isGuest = $getQuoteById->getCustomerIsGuest();

            if ($customerId && $isGuest == 1) {
                $getQuoteById->setCustomerIsGuest(false);
                $getQuoteById->save();
            }
        }

        if ($order != null) {
            $this->cancelOrder($order, $message);
        }

        return $this->redirectWithErrorMessage($message);
    }

    protected function getOrder($orderId): Order
    {
        $order = ObjectManagerHelper::get(Order::class);
        return $order->loadByIncrementId($orderId);
    }

    /**
     * @param $tokenWs
     *
     * @return WebpayOrderData
     */
    private function getWebpayOrderData($tokenWs): WebpayOrderData
    {
        $webpayOrderDataModel = $this->webpayOrderDataFactory->create();
        return $webpayOrderDataModel->load($tokenWs, 'token');
    }

    private function createCommitHistoryComment($commitResponse): string
    {
        if ($commitResponse instanceof TransactionCommitResponse) {
            $transactionLocalDate = TbkResponseHelper::utcToLocalDate($commitResponse->getTransactionDate());
            $commitStatus = $commitResponse->getResponseCode() == 0 ? 'Aprobada' : 'Rechazada';
            $installmentsAmount = $commitResponse->getInstallmentsAmount();
            $balance = $commitResponse->getBalance();
            $historyComment =  '<strong>Transacción ' . $commitStatus . '</strong><br><br>' .
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
