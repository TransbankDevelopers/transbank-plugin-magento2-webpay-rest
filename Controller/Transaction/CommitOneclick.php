<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Transbank\Webpay\Model\LogHandler;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;

/**
 * Controller for commit transaction Webpay.
 */
class CommitOneclick extends \Magento\Framework\App\Action\Action
{
    // protected $paymentTypeCodearray = [
    //     'VD' => 'Venta Debito',
    //     'VN' => 'Venta Normal',
    //     'VC' => 'Venta en cuotas',
    //     'SI' => '3 cuotas sin interés',
    //     'S2' => '2 cuotas sin interés',
    //     'NC' => 'N cuotas sin interés',
    // ];
    protected $configProvider;

    protected $quoteRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $OneclickInscriptionDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->OneclickInscriptionDataFactory = $OneclickInscriptionDataFactory;
        $this->log = new LogHandler();
    }

    /**
     * @Override
     */
    public function execute()
    {
        $config = $this->configProvider->getPluginConfig();
        $orderStatusCanceled = $this->configProvider->getOrderErrorStatus();
        $inscriptionResult = [];

        try {
            $tbkToken = $_POST['TBK_TOKEN'] ?? $_GET['TBK_TOKEN'] ?? null;
            // if (empty($tbkToken)) {
            //     return $this->orderCanceledByUser($_GET['TBK_TOKEN'], $_GET['TBK_ID_SESION'], $orderStatusCanceled);
            // }

            if (is_null($tbkToken)) {
                throw new \Exception('Token no encontrado');
            }

            list($OneclickInscriptionData, $order) = $this->getOrderByToken($tbkToken);
            
            $status = $OneclickInscriptionData->getStatus();
            if ($status == OneclickInscriptionData::PAYMENT_STATUS_WATING) {
                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
                $inscriptionResult = $transbankSdkWebpay->finishInscription($tbkToken);
                $OneclickInscriptionData->setMetadata(json_encode($inscriptionResult));

                if (isset($inscriptionResult->tbkUser) && isset($inscriptionResult->responseCode) && $inscriptionResult->responseCode == 0) {
                    $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_SUCCESS);
                    $OneclickInscriptionData->setResponseCode($inscriptionResult->responseCode);
                    $OneclickInscriptionData->setTbkUser($inscriptionResult->tbkUser);
                    $OneclickInscriptionData->setAuthorizationCode($inscriptionResult->authorizationCode);
                    $OneclickInscriptionData->setCardType($inscriptionResult->cardType);
                    $OneclickInscriptionData->setCardNumber($inscriptionResult->cardNumber);

                    $OneclickInscriptionData->save();

                    // $authorizationCode = $inscriptionResult->authorizationCode;
                    // $payment = $order->getPayment();
                    // $payment->setLastTransId($authorizationCode);
                    // $payment->setTransactionId($authorizationCode);
                    // $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $inscriptionResult]);

                    // $orderStatus = $this->configProvider->getOrderSuccessStatus();
                    // $order->setState($orderStatus)->setStatus($orderStatus);
                    // $order->addStatusToHistory($order->getStatus(), json_encode($inscriptionResult));
                    // $order->save();

                    // $this->checkoutSession->getQuote()->setIsActive(false)->save();

                    // $message = $this->getSuccessMessage($this->commitResponseToArray($inscriptionResult));
                    $message = "Success - Oneclick Inscription";
                    $this->messageManager->addSuccess(__($message));


                    return $this->resultRedirectFactory->create()->setPath('checkout');
                } else {
                    $OneclickInscriptionData->setPaymentStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);
                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);

                    $order->addStatusToHistory($order->getStatus(), json_encode($inscriptionResult));
                    $order->save();

                    $this->checkoutSession->restoreQuote();

                    $message = $this->getRejectMessage($this->commitResponseToArray($inscriptionResult));
                    $this->messageManager->addError(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            } else {
                $inscriptionResult = json_decode($OneclickInscriptionData->getMetadata(), true);

                if ($status == OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
                    // $message = $this->getSuccessMessage($inscriptionResult);
                    $message = "Success - Oneclick Inscription";
                    $this->messageManager->addSuccess(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                } elseif ($status == OneclickInscriptionData::PAYMENT_STATUS_FAILED) {
                    $this->checkoutSession->restoreQuote();
                    $message = $this->getRejectMessage($inscriptionResult);
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
            'responseCode'          => $response->responseCode,
            'tbkUser'               => $response->tbkUser,
            'authorizationCode'     => $response->authorizationCode,
            'cardType'              => $response->cardType,
            'cardNumber'            => $response->cardNumber,
        ];
    }

    protected function getSuccessMessage(array $inscriptionResult)
    {
        if ($inscriptionResult['responseCode'] == 0) {
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
                <b>N&uacute;mero de cuotas: </b>{$transactionResult['installmentsNumber']}<br>
                <b>Monto Cuota: </b>{$transactionResult['installmentsAmount']}<br>
        ";
        }

        $message = "<h2>Detalles del pago con Webpay</h2>
        <p>
            <br>
            <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResponse}<br>
            <b>C&oacute;digo de la Transacci&oacute;n: </b>{$transactionResult['responseCode']}<br>
            <b>Monto:</b> $ {$transactionResult['amount']}<br>
            <b>Order de Compra: </b> {$transactionResult['buyOrder']}<br>
            <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($transactionResult['transactionDate'])).'<br>
            <b>Hora de la Transacci&oacute;n: </b>'.date('H:i:s', strtotime($transactionResult['transactionDate']))."<br>
            <b>Tarjeta: </b>**** **** **** {$transactionResult['cardDetail']['card_number']}<br>
            <b>C&oacute;digo de autorizacion: </b>{$transactionResult['authorizationCode']}<br>
            <b>Tipo de Pago: </b>{$paymentType}<br>
            <b>Tipo de Cuotas: </b>{$tipoCuotas}<br>
            {$installmentsString}
        </p>";

        return $message;
    }

    protected function orderCanceledByUser($token, $quoteId, $orderStatusCanceled)
    {
        list($OneclickInscriptionData, $order) = $this->getOrderByToken($token);
        $message = 'Orden cancelada por el usuario';
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
    private function getOrderByToken($tbkToken)
    {
        $OneclickInscriptionDataModel = $this->OneclickInscriptionDataFactory->create();
        $OneclickInscriptionData = $OneclickInscriptionDataModel->load($tbkToken, 'token');
        $orderId = $OneclickInscriptionData->getOrderId();
        $order = $this->getOrder($orderId);

        return [$OneclickInscriptionData, $order];
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
