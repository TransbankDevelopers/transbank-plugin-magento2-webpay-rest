<?php
namespace Transbank\Webpay\Controller\Transaction;

use Transbank\Webpay\Model\TransbankSdkWebpay;
use Transbank\Webpay\Model\LogHandler;

use \Magento\Sales\Model\Order;
use Transbank\Webpay\Model\WebpayOrderData;

/**
 * Controller for commit transaction Webpay
 */
class CommitWebpayM22 extends \Magento\Framework\App\Action\Action
{
    
    protected $paymentTypeCodearray = [
        "VD" => "Venta Debito",
        "VN" => "Venta Normal",
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés",
    ];
    protected $configProvider;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context, \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory
    ) {
        
        parent::__construct($context);
        
        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->log = new LogHandler();
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
            $tokenWs = isset($_POST['token_ws']) ? $_POST['token_ws'] : null;
            if (isset($_POST['TBK_TOKEN'])) {
                return $this->orderCanceledByUser($_POST['TBK_TOKEN'], $orderStatusCanceled);
            }
            
            if (is_null($tokenWs)) {
                throw new \Exception('Token no encontrado');
            }
    
            list($webpayOrderData, $order) = $this->getOrderByToken($tokenWs);
    
            $paymentStatus = $webpayOrderData->getPaymentStatus();
            if ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_WATING) {
                
                $transbankSdkWebpay = new TransbankSdkWebpay($config);
                $transactionResult = $transbankSdkWebpay->commitTransaction($tokenWs);
                
                $webpayOrderData->setMetadata(json_encode($transactionResult));
                
                if (isset($transactionResult->buyOrder) && isset($transactionResult->detailOutput) && $transactionResult->detailOutput->responseCode == 0) {
    
                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_SUCCESS);
                    $webpayOrderData->save();
                    
                    $authorizationCode = $transactionResult->detailOutput->authorizationCode;
                    $payment = $order->getPayment();
                    $payment->setLastTransId($authorizationCode);
                    $payment->setTransactionId($authorizationCode);
                    $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$transactionResult]);
                    
                    $orderStatus = $this->configProvider->getOrderSuccessStatus();
                    $order->setState($orderStatus)->setStatus($orderStatus);
                    $order->addStatusToHistory($order->getStatus(), json_encode($transactionResult));
                    $order->save();
                    
                    $this->checkoutSession->getQuote()->setIsActive(false)->save();
                    
                    return $this->toRedirect($transactionResult->urlRedirection, ['token_ws' => $tokenWs]);
                    
                } else {
    
                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_FAILED);
                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);
                    
                    $order->addStatusToHistory($order->getStatus(), json_encode($transactionResult));
                    $order->save();
                    
                    $this->checkoutSession->restoreQuote();
                    
                    $message = $this->getRejectMessage(json_decode(json_encode($transactionResult), true));
                    $this->messageManager->addError(__($message));
                    
                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
                
            } else {
                
                $transactionResult = json_decode($webpayOrderData->getMetadata(), true);
                
                if ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_SUCCESS) {
                    
                    $message = $this->getSuccessMessage($transactionResult);
                    $this->messageManager->addSuccess(__($message));
                    
                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                    
                } elseif ($paymentStatus == WebpayOrderData::PAYMENT_STATUS_FAILED) { {
                        $this->checkoutSession->restoreQuote();
                        $message = $this->getRejectMessage($transactionResult);
                        $this->messageManager->addError(__($message));
                        
                        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                    }
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
            $content .= "<input type='hidden' name='" . htmlentities($name) . "' value='" . htmlentities($value) . "'>";
        }
        $content .= "</form>";
        $content .= "<script language='JavaScript'>document.webpayForm.submit();</script>";
        $response->setContents($content);
        
        return $response;
    }
    
    protected function getSuccessMessage(array $transactionResult)
    {
        
        if ($transactionResult['detailOutput']['paymentTypeCode'] == "SI" || $transactionResult['detailOutput']['paymentTypeCode'] == "S2" || $transactionResult['detailOutput']['paymentTypeCode'] == "NC" || $transactionResult['detailOutput']['paymentTypeCode'] == "VC") {
            $tipoCuotas = $this->paymentTypeCodearray[$transactionResult['detailOutput']['paymentTypeCode']];
        } else {
            $tipoCuotas = "Sin cuotas";
        }
        
        if ($transactionResult['detailOutput']['responseCode'] == 0) {
            $transactionResponse = "Transacci&oacute;n Aprobada";
        } else {
            $transactionResponse = "Transacci&oacute;n Rechazada";
        }
        
        if ($transactionResult['detailOutput']['paymentTypeCode'] == "VD") {
            $paymentType = "Débito";
        } else {
            $paymentType = "Crédito";
        }
        
        $message = "<h2>Detalles del pago con Webpay</h2>
        <p>
            <br>
            <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResponse}<br>
            <b>C&oacute;digo de la Transacci&oacute;n: </b>{$transactionResult['detailOutput']['responseCode']}<br>
            <b>Monto:</b> $ {$transactionResult['detailOutput']['amount']}<br>
            <b>Order de Compra: </b> {$transactionResult['detailOutput']['buyOrder']}<br>
            <b>Fecha de la Transacci&oacute;n: </b>" . date('d-m-Y', strtotime($transactionResult['transactionDate'])) . "<br>
            <b>Hora de la Transacci&oacute;n: </b>" . date('H:i:s', strtotime($transactionResult['transactionDate'])) . "<br>
            <b>Tarjeta: </b>**** **** **** {$transactionResult['cardDetail']['cardNumber']}<br>
            <b>C&oacute;digo de autorizacion: </b>{$transactionResult['detailOutput']['authorizationCode']}<br>
            <b>Tipo de Pago: </b>{$paymentType}<br>
            <b>Tipo de Cuotas: </b>{$tipoCuotas}<br>
            <b>N&uacute;mero de cuotas: </b>{$transactionResult['detailOutput']['sharesNumber']}
        </p>";
        
        return $message;
    }
    
    protected function orderCanceledByUser($token, $orderStatusCanceled) {
        list($webpayOrderData, $order) = $this->getOrderByToken($token);
        $message = 'Orden cancelada por el usuario';
        $this->checkoutSession->restoreQuote();
        $this->messageManager->addError(__($message));
        
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
        if (isset($transactionResult['detailOutput'])) {
            $message = "<h2>Transacci&oacute;n rechazada con Webpay</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResult['detailOutput']['responseCode']}<br>
                <b>Monto:</b> $ {$transactionResult['detailOutput']['amount']}<br>
                <b>Order de Compra: </b> {$transactionResult['detailOutput']['buyOrder']}<br>
                <b>Fecha de la Transacci&oacute;n: </b>" . date('d-m-Y', strtotime($transactionResult['transactionDate'])) . "<br>
                <b>Hora de la Transacci&oacute;n: </b>" . date('H:i:s', strtotime($transactionResult['transactionDate'])) . "<br>
                <b>Tarjeta: </b>**** **** **** {$transactionResult['cardDetail']['cardNumber']}<br>
                <b>Mensaje de Rechazo: </b>{$transactionResult['detailOutput']['responseDescription']}
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
                $message = "<h2>Transacci&oacute;n Fallida</h2>";
                
                return $message;
            }
        }
    }
    
    protected function getOrder($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        return $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
    }
    /**
     * @param $tokenWs
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
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function errorOnConfirmation(\Exception $e, $order, $orderStatusCanceled)
    {
        $message = 'Error al confirmar transacción: ' . $e->getMessage();
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
