<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Helper\DateHelper;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;

/**
 * Controller for commit transaction Webpay.
 */
class CommitWebpay extends \Magento\Framework\App\Action\Action
{
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
    protected $webpayOrderDataFactory;
    protected $log;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
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
        $config = $this->configProvider->getPluginConfig();
        $orderStatusCanceled = $this->configProvider->getOrderErrorStatus();
        $transactionResult = [];

        try {
            $tokenWs = $_POST['token_ws'] ?? $_GET['token_ws'] ?? null;

            $params = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
            if (isset($_POST['TBK_TOKEN'])) {
                $this->log->logError('C.2. Error tipo Flujo 2: El pago fue anulado por tiempo de espera => tbkIdSesion: '
                    . $_POST['TBK_ID_SESION']);
                return $this->orderCanceledByUser($_POST['TBK_TOKEN'], $_POST['TBK_ID_SESION'], $orderStatusCanceled);
            }
            if (isset($_GET['TBK_TOKEN'])) {
                $this->log->logError('C.2. Error tipo Flujo 2: El pago fue anulado por tiempo de espera => tbkIdSesion: '.
                    $_GET['TBK_ID_SESION']); // Logs
                return $this->orderCanceledByUser($_GET['TBK_TOKEN'], $_GET['TBK_ID_SESION'], $orderStatusCanceled);
            }

            if (is_null($tokenWs)) {
                throw new \Exception('Token no encontrado');
            }
            $this->log->logInfo('C.1. Iniciando validación luego de redirección desde tbk => method: '
                .$_SERVER['REQUEST_METHOD']);
            $this->log->logInfo(json_encode($params));

            list($webpayOrderData, $order) = $this->getOrderByToken($tokenWs);
            $paymentStatus = $webpayOrderData->getPaymentStatus();
            if ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_WATING) {
                $this->log->logInfo('C.3. Transaccion antes del commit  => token: '.$tokenWs);
                $this->log->logInfo(json_encode($webpayOrderData));

                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
                $transactionResult = $transbankSdkWebpay->commitTransaction($tokenWs); // Commit
                $webpayOrderData->setMetadata(json_encode($transactionResult));

                $this->log->logInfo('C.2. Tx obtenido desde la tabla webpay_transactions => token: '.$tokenWs);
                $this->log->logInfo(json_encode($webpayOrderData));

                if (isset($transactionResult->buyOrder) &&
                    isset($transactionResult->responseCode) &&
                    $transactionResult->responseCode == 0
                    ) {
                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_SUCCESS);
                    $webpayOrderData->save();

                    $authorizationCode = $transactionResult->authorizationCode;
                    $payment = $order->getPayment();
                    $payment->setLastTransId($authorizationCode);
                    $payment->setTransactionId($authorizationCode);
                    $payment->setAdditionalInformation(
                        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $transactionResult]);

                    $orderStatus = $this->configProvider->getOrderSuccessStatus();
                    $order->setState($orderStatus)->setStatus($orderStatus);
                    $order->addStatusToHistory($order->getStatus(), json_encode($transactionResult));
                    $order->save();

                    $this->log->logInfo('C.5. Transacción con commit exitoso en Transbank y guardado => token: '
                        .$tokenWs);

                    $this->checkoutSession->getQuote()->setIsActive(false)->save();

                    $message = $this->getSuccessMessage($this->commitResponseToArray($transactionResult));

                    $this->messageManager->addComplexSuccessMessage(
                        'successMessage',
                        [
                            'message' => $message
                        ]
                    );

                    $this->log->logInfo('TRANSACCION VALIDADA POR MAGENTO Y POR TBK EN ESTADO STATUS_APPROVED => TOKEN: '
                        .$tokenWs);
                    $this->log->logInfo(json_encode($transactionResult));

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                } else {
                    $this->log->logError('C.5. Respuesta de tbk commit fallido => token: '.$tokenWs);
                    $this->log->logError(json_encode($transactionResult));

                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_FAILED);
                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);
                    $commitHistoryComment = $this->createCommitHistoryComment($transactionResult);
                    $order->addStatusToHistory($order->getStatus(), $commitHistoryComment);
                    $order->save();

                    $this->checkoutSession->restoreQuote();
                    $message = $this->getRejectMessage($transactionResult);
                    $this->messageManager->addError(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            } else {
                $transactionResult = json_decode($webpayOrderData->getMetadata(), true);

                if ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_SUCCESS) {
                    $message = $this->getSuccessMessage($transactionResult);

                    $this->messageManager->addComplexSuccessMessage(
                        'successMessage',
                        [
                            'message' => $message
                        ]
                    );

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                } elseif ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_FAILED) {
                    $this->checkoutSession->restoreQuote();
                    $message = $this->getRejectMessage($transactionResult);
                    $this->messageManager->addError(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            }
        } catch (\Exception $e) {
            $order = isset($order) ? $order : null;

            return $this->errorOnConfirmation($e, $order, $orderStatusCanceled);
        }
    }

    protected function toRedirect($url, $data)
    {
        $response = $this->resultRawFactory->create();
        $content = "<form action='$url' method='POST' name='webpayForm'>";
        foreach ($data as $name => $value) {
            $content .= "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
        }
        $content .= '</form>';
        $content .= "<script language='JavaScript'>document.webpayForm.submit();</script>";
        $response->setContents($content);

        return $response;
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

    protected function getSuccessMessage(array $transactionResult)
    {
        if ( in_array($transactionResult['paymentTypeCode'], ['SI', 'S2', 'NC', 'VC']) ) {
            $tipoCuotas = $this->paymentTypeCodearray[$transactionResult['paymentTypeCode']];
        } else {
            $tipoCuotas = 'Sin cuotas';
        }

        if ($transactionResult['responseCode'] == 0) {
            $transactionResponse = 'Transacci&oacute;n Aprobada';
        } else {
            $transactionResponse = 'Transacci&oacute;n Rechazada';
        }

        if ($transactionResult['paymentTypeCode'] == 'VD') {
            $paymentType = 'Débito';
        } elseif ($transactionResult['paymentTypeCode'] == 'VP') {
            $paymentType = 'Prepago';
        } else {
            $paymentType = 'Crédito';
        }
        $installmentsString = '';
        if ($tipoCuotas != 'Sin cuotas') {
            $installmentsString = "
                <div>
                    • N&uacute;mero de cuotas: <b>{$transactionResult['installmentsNumber']}</b>
                </div>
                <div>
                    • Monto Cuota: <b>{$transactionResult['installmentsAmount']}</b>
                </div>
            ";
        }

        return "
            <b>Detalles del pago con Webpay</b>
            <div>
                • Respuesta de la Transacci&oacute;n: <b>{$transactionResponse}</b>
            </div>
            <div>
                • C&oacute;digo de la Transacci&oacute;n: <b>{$transactionResult['responseCode']}</b>
            </div>
            <div>
                • Monto: <b>$ {$transactionResult['amount']}</b>
            </div>
            <div>
                • Order de Compra: <b>$ {$transactionResult['buyOrder']}</b>
            </div>
            <div>
                • Fecha de la Transacci&oacute;n: <b>".date('d-m-Y', strtotime($transactionResult['transactionDate'])).'</b>
            </div>
            <div>
                • Hora de la Transacci&oacute;n: <b>'.date('H:i:s', strtotime($transactionResult['transactionDate']))."</b>
            </div>
            <div>
                • Tarjeta: <b>**** **** **** {$transactionResult['cardDetail']['card_number']}</b>
            </div>
            <div>
                • C&oacute;digo de autorizacion: <b>{$transactionResult['authorizationCode']}</b>
            </div>
            <div>
                • Tipo de Pago: <b>{$paymentType}</b>
            </div>
            <div>
                • Tipo de Cuotas: <b>{$tipoCuotas}</b>
            </div>
            {$installmentsString}
            ";
    }

    protected function orderCanceledByUser($token, $quoteId, $orderStatusCanceled)
    {
        $message = 'Orden cancelada por el usuario';
        $this->messageManager->addError(__($message));
        list($webpayOrderData, $order) = $this->getOrderByToken($token);

        if ($order->getStatus() == $orderStatusCanceled){
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $this->checkoutSession->restoreQuote();
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
            $order->cancel();
            $order->save();
            $order->setStatus($orderStatusCanceled);
            $order->addStatusToHistory($order->getStatus(), $message);
            $order->save();
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    /**
     * @param array|TransactionCommitResponse $transactionResult
     * @return string
     */
    protected function getRejectMessage( $transactionResult ): string
    {
        if (isset($transactionResult) && $transactionResult instanceof TransactionCommitResponse) {
            return "<b>Transacci&oacute;n rechazada por Webpay</b>
                <div>
                    • Respuesta de la Transacci&oacute;n: <b>{$transactionResult->getResponseCode()}</b>
                </div>
                <div>
                    • Monto: <b>$ {$transactionResult->getAmount()}</b>
                </div>
                <div>
                    • Orden de Compra: <b>{$transactionResult->getBuyOrder()}</b>
                </div>
                <div>
                    • Fecha de la Transacci&oacute;n: <b>"
                        . date('d-m-Y', strtotime($transactionResult->getTransactionDate())) ."</b>
                </div>
                <div>
                    • Hora de la Transacci&oacute;n: <b>"
                        . date('H:i:s', strtotime($transactionResult->getTransactionDate())) ."</b>
                </div>
                <div>
                    • Tarjeta: <b>**** **** **** {$transactionResult->getCardNumber()}</b>
                </div>";

        }

        if (isset($transactionResult['error'])) {
            $error = $transactionResult['error'];
            $detail = isset($transactionResult['detail']) ? $transactionResult['detail'] : 'Sin detalles';
            return "<b>Transacci&oacute;n fallida con Webpay</b>
                <div>
                    • Respuesta de la Transacci&oacute;n: <b>{$error}</b>
                </div>
                <div>
                    • Mensaje: <b>{$detail}</b>
                </div>";
        }

        return '<h2>Transacci&oacute;n Fallida</h2>';

    }

    protected function getOrder($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        return $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($orderId);
    }

    /**
     * @param $tokenWs
     *
     * @return array
     */
    private function getOrderByToken($tokenWs)
    {
        $webpayOrderDataModel = $this->webpayOrderDataFactory->create();
        $webpayOrderData = $webpayOrderDataModel->load($tokenWs, 'token');
        $orderId = $webpayOrderData->getOrderId();
        $order = $this->getOrder($orderId);

        return [$webpayOrderData, $order];
    }

    /**
     * @param \Exception $e
     * @param $order
     * @param $orderStatusCanceled
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function errorOnConfirmation(\Exception $e, $order, $orderStatusCanceled)
    {
        $message = 'Error al confirmar transacción: '.$e->getMessage();
        $this->log->logError($message);
        $this->checkoutSession->restoreQuote();
        $this->messageManager->addError(__($message));
        if ($order != null && $order->getState() != Order::STATE_PROCESSING) {
            $order->cancel();
            $order->save();
            $order->setStatus($orderStatusCanceled);
            $order->addStatusToHistory($order->getStatus(), $message);
            $order->save();
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    private function createCommitHistoryComment($commitResponse): string {
        if ( $commitResponse instanceof TransactionCommitResponse ) {
            $transactionLocalDate = DateHelper::utcToLocalDate($commitResponse->getTransactionDate());
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
