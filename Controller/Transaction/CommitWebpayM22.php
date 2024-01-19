<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Transbank\Webpay\Model\LogHandler;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Helper\InteractsWithFullLog;

/**
 * Controller for commit transaction Webpay.
 */
class CommitWebpayM22 extends \Magento\Framework\App\Action\Action
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
    protected $interactsWithFullLog;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory,
        InteractsWithFullLog $InteractsWithFullLog
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
        $this->log = new LogHandler();
        $this->interactsWithFullLog = $InteractsWithFullLog;
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
                $this->interactsWithFullLog->logWebpayPlusRetornandoDesdeTbkFujo2Error($_POST['TBK_ID_SESION']); // Logs
                return $this->orderCanceledByUser($_POST['TBK_TOKEN'], $_POST['TBK_ID_SESION'], $orderStatusCanceled);
            }
            if (isset($_GET['TBK_TOKEN'])) {
                $this->interactsWithFullLog->logWebpayPlusRetornandoDesdeTbkFujo2Error($_GET['TBK_ID_SESION']); // Logs
                return $this->orderCanceledByUser($_GET['TBK_TOKEN'], $_GET['TBK_ID_SESION'], $orderStatusCanceled);
            }

            if (is_null($tokenWs)) {
                throw new \Exception('Token no encontrado');
            }

            $this->interactsWithFullLog->logWebpayPlusRetornandoDesdeTbk($_SERVER['REQUEST_METHOD'], $params); // Logs

            list($webpayOrderData, $order) = $this->getOrderByToken($tokenWs);

            $paymentStatus = $webpayOrderData->getPaymentStatus();
            if ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_WATING) {
                $this->interactsWithFullLog->logWebpayPlusAntesCommitTx($tokenWs, $webpayOrderData, $this->cart); // Logs

                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
                $transactionResult = $transbankSdkWebpay->commitTransaction($tokenWs); // Commit
                $webpayOrderData->setMetadata(json_encode($transactionResult));

                $this->interactsWithFullLog->logWebpayPlusDespuesObtenerTx($tokenWs, $webpayOrderData); // Logs

                if (isset($transactionResult->buyOrder) && isset($transactionResult->responseCode) && $transactionResult->responseCode == 0) {
                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_SUCCESS);
                    $webpayOrderData->save();

                    $authorizationCode = $transactionResult->authorizationCode;
                    $payment = $order->getPayment();
                    $payment->setLastTransId($authorizationCode);
                    $payment->setTransactionId($authorizationCode);
                    $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $transactionResult]);

                    $orderStatus = $this->configProvider->getOrderSuccessStatus();
                    $order->setState($orderStatus)->setStatus($orderStatus);
                    $order->addStatusToHistory($order->getStatus(), json_encode($transactionResult));
                    $order->save();

                    $this->interactsWithFullLog->logWebpayPlusGuardandoCommitExitoso($tokenWs); // Logs

                    $this->checkoutSession->getQuote()->setIsActive(false)->save();

                    $message = $this->getSuccessMessage($this->commitResponseToArray($transactionResult));

                    $this->messageManager->addComplexSuccessMessage(
                        'successMessage',
                        [
                            'message' => $message
                        ]
                    );

                    $this->interactsWithFullLog->logWebpayPlusTodoOk($tokenWs, $transactionResult); // Logs

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                } else {
                    $this->interactsWithFullLog->logWebpayPlusCommitFallidoError($tokenWs, $transactionResult); // Logs

                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_FAILED);
                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);

                    $order->addStatusToHistory($order->getStatus(), json_encode($transactionResult));
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
        if ($transactionResult['paymentTypeCode'] == 'SI' || $transactionResult['paymentTypeCode'] == 'S2' || $transactionResult['paymentTypeCode'] == 'NC' || $transactionResult['paymentTypeCode'] == 'VC') {
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

        $message = "
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

        return $message;
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

    protected function getRejectMessage(array $transactionResult)
    {
        if (isset($transactionResult)) {
            $message = "<h2>Transacci&oacute;n rechazada con Webpay</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResult['responseCode']}<br>
                <b>Monto:</b> $ {$transactionResult['amount']}<br>
                <b>Order de Compra: </b> {$transactionResult['buyOrder']}<br>
                <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($transactionResult['transactionDate'])).'<br>
                <b>Hora de la Transacci&oacute;n: </b>'.date('H:i:s', strtotime($transactionResult['transactionDate']))."<br>
                <b>Tarjeta: </b>**** **** **** {$transactionResult['cardDetail']['card_number']}<br>
            </p>";

            return $message;
        } else {
            if (isset($transactionResult['error'])) {
                $error = $transactionResult['error'];
                $detail = isset($transactionResult['detail']) ? $transactionResult['detail'] : 'Sin detalles';
                $message = "<h2>Transacci&oacute;n fallida con Webpay</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$error}<br>
                <b>Mensaje: </b>{$detail}
            </p>";

                return $message;
            } else {
                $message = '<h2>Transacci&oacute;n Fallida</h2>';

                return $message;
            }
        }
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
}
