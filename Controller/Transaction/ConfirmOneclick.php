<?php

namespace Transbank\Webpay\Controller\Transaction;

use Transbank\Webpay\Model\LogHandler;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\WebpayOrderData;

/**
 * Controller for create Oneclick Inscription.
 */
class ConfirmOneclick extends \Magento\Framework\App\Action\Action
{
    protected $configProvider;

    /**
     * ConfirmOneclick constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Checkout\Model\Cart                     $cart
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Quote\Model\QuoteManagement             $quoteManagement
     * @param \Magento\Store\Model\StoreManagerInterface       $storeManager
     * @param \Transbank\Webpay\Model\Config\ConfigProvider    $configProvider
     * @param \Transbank\Webpay\Model\OneclickInscriptionDataFactory   $OneclickInscriptionDataFactory
     * @param \Transbank\Webpay\Model\WebpayOrderDataFactory   $webpayOrderDataFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $OneclickInscriptionDataFactory,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->OneclickInscriptionDataFactory = $OneclickInscriptionDataFactory;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->log = new LogHandler();
    }

    /**
     * @throws \Exception
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $response = null;
        $orderStatusCanceled = $this->configProvider->getOrderErrorStatus();
        $orderStatusSuccess = $this->configProvider->getOrderSuccessStatus();

        try {
            $resultJson = $this->resultJsonFactory->create();

            if (isset($_POST['inscription'])) {
                $inscriptionId = intval($_POST['inscription']);
            } else {
                return $resultJson->setData(['status' => 'error', 'message' => 'Error autorizando transacción']);
            }

            list($username, $tbkUser) = $this->getOneclickInscriptionData($inscriptionId);

            $config = $this->configProvider->getPluginConfigOneclick();

            $tmpOrder = $this->getOrder();
            $this->checkoutSession->restoreQuote();

            $quote = $this->cart->getQuote();

            $quote->getPayment()->importData(['method' => Oneclick::CODE]);
            $quote->collectTotals();
            $order = $tmpOrder;
            if ($tmpOrder != null && $tmpOrder->getStatus() == $orderStatusCanceled) {
                $order = $this->quoteManagement->submit($quote);
            }
            $grandTotal = round($order->getGrandTotal());

            // $this->checkoutSession->setLastQuoteId($quote->getId());
            // $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            // $this->checkoutSession->setLastOrderId($order->getId());
            // $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            // $this->checkoutSession->setLastOrderStatus($order->getStatus());
            // $this->checkoutSession->setGrandTotal($grandTotal);

            $quoteId = $quote->getId();
            $orderId = $order->getId();
            $customerId = $order->getCustomerId();

            $quote->save();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($config);

            $details = [
                [
                    // "commerce_code" => $config['COMMERCE_CODE'],
                    "commerce_code" => '597055555542',
                    "buy_order" => $orderId,
                    "amount" => $grandTotal,
                    "installments_number" => 1
                ]
            ]; 

            $response = $transbankSdkWebpay->authorizeTransaction($customerId, $username, $tbkUser, $details);
            // $response = (object)array($response);
            $dataLog = ['customerId' => $username, 'orderId' => $orderId];

            if (isset($response->details) && $response->details[0]->responseCode == 0) {

                $webpayOrderData = $this->saveWebpayData(
                    $response->buyOrder, 
                    $response->details[0]->buyOrder, 
                    $response->details[0]->commerceCode, 
                    WebpayOrderData::PAYMENT_STATUS_SUCCESS, 
                    $orderId, 
                    $quoteId,
                    $response
                );

                $message = '<h3>Pago autorizado exitosamente con Oneclick</h3><br>'.json_encode($dataLog);
                $payment = $order->getPayment();
                $payment->setLastTransId($response->details[0]->authorizationCode);
                $payment->setTransactionId($response->details[0]->authorizationCode);
                $payment->setAdditionalInformation([\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $response]);

                $order->setState($orderStatusSuccess)->setStatus($orderStatusSuccess);
                $order->addStatusToHistory($order->getStatus(), $message);

                $order->save();

                $this->checkoutSession->getQuote()->setIsActive(false)->save();

                $message = $this->getSuccessMessage($response);
                $order->addStatusToHistory($order->getStatus(), $message);

                $this->messageManager->addSuccess(__($message));
                $order->save();

                // return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                return $resultJson->setData(['status' => 'success', 'response' => $response]);

            } else {
                $webpayOrderData = $this->saveWebpayData(
                    '', 
                    '', 
                    '', 
                    WebpayOrderData::PAYMENT_STATUS_FAILED, 
                    $orderId, 
                    $quoteId,
                    $response
                );

                $order->cancel();
                $order->setStatus($orderStatusCanceled);
                $message = '<h3>Error en Inscripción con Oneclick</h3><br>'.json_encode($response);

                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();

                $this->checkoutSession->restoreQuote();

                $message = $this->getRejectMessage($response);
                $this->messageManager->addError(__($message));

                // return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                return $resultJson->setData(['status' => 'error', 'response' => $response]);
            }

        } catch (\Exception $e) {
            $message = 'Error al crear transacción: '.$e->getMessage();
            return $resultJson->setData(['status' => 'error', 'message' => $message]);

            $this->log->logError($message);
            $response = ['error' => $message];
            if ($order != null) {
                $order->cancel();
                $order->save();
                $order->setStatus($orderStatusCanceled);
                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();
            }

            return $resultJson->setData(['status' => 'error', 'response' => $response]);
        }

    }

    /**
     * @return |null
     */
    protected function getOrder()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId == null) {
                return null;
            }
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            return $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $inscriptionId
     *
     * @throws \Exception
     *
     * @return OneclickOrderData
     */
    protected function getOneclickInscriptionData($inscriptionId)
    {
        $OneclickInscriptionDataModel = $this->OneclickInscriptionDataFactory->create();
        $OneclickInscriptionData = $OneclickInscriptionDataModel->load($inscriptionId, 'id');
        $tbkUser = $OneclickInscriptionData->getTbkUser();
        $username = $OneclickInscriptionData->getUsername();

        return [$username, $tbkUser];
    }

    /**
     * @return string
     */
    protected function getOrderId()
    {
        return $this->checkoutSession->getLastRealOrderId();
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param $guestEmail
     */
    private function setQuoteData(\Magento\Quote\Model\Quote $quote, $guestEmail)
    {
        $quote->getBillingAddress()->setEmail($guestEmail);
        $quote->setData('customer_email', $quote->getBillingAddress()->getEmail());
        $quote->setData('customer_firstname', $quote->getBillingAddress()->getFirstName());
        $quote->setData('customer_lastname', $quote->getBillingAddress()->getLastName());
        $quote->setData('customer_is_guest', 1);
    }

    /**
     * @param $buyOrder
     * @param $childBuyOrder
     * @param $commerceCode
     * @param $payment_status
     * @param $order_id
     * @param $quote_id
     *
     * @throws \Exception
     *
     * @return WebpayOrderData
     */
    protected function saveWebpayData($buyOrder, $childBuyOrder, $commerceCode, $payment_status, $order_id, $quote_id, $response)
    {
        $webpayOrderData = $this->webpayOrderDataFactory->create();
        $webpayOrderData->setData([
            'buy_order'       => $buyOrder,
            'child_buy_order' => $childBuyOrder,
            'commerce_code'   => $commerceCode,
            'payment_status'  => $payment_status,
            'order_id'        => $order_id,
            'quote_id'        => $quote_id,
            'metadata'        => json_encode($response),
        ]);
        $webpayOrderData->save();

        return $webpayOrderData;
    }

    protected function getSuccessMessage($transactionResult)
    {
        if ($transactionResult->details[0]->responseCode == 0) {
            $transactionResponse = 'Transacci&oacute;n Aprobada';
        } else {
            $transactionResponse = 'Transacci&oacute;n Rechazada';
        }

        if ($transactionResult->details[0]->paymentTypeCode == 'VD') {
            $paymentType = 'Débito';
        } elseif ($transactionResult->details[0]->paymentTypeCode == 'VP') {
            $paymentType = 'Prepago';
        } else {
            $paymentType = 'Crédito';
        }

        $message = "<h2>Detalles del pago con Oneclick</h2>
        <p>
            <br>
            <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResponse}<br>
            <b>C&oacute;digo de la Transacci&oacute;n: </b>{$transactionResult->details[0]->responseCode}<br>
            <b>Monto:</b> $ {$transactionResult->details[0]->amount}<br>
            <b>Order de Compra: </b> {$transactionResult->details[0]->buyOrder}<br>
            <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($transactionResult->transactionDate)).'<br>
            <b>Hora de la Transacci&oacute;n: </b>'.date('H:i:s', strtotime($transactionResult->transactionDate))."<br>
            <b>Tarjeta: </b>**** **** **** {$transactionResult->cardNumber}<br>
            <b>C&oacute;digo de autorizacion: </b>{$transactionResult->details[0]->authorizationCode}<br>
            <b>Tipo de Pago: </b>{$paymentType}<br>
        </p>";

        return $message;
    }

    protected function getRejectMessage($transactionResult)
    {
        if (isset($transactionResult)) {
            $message = "<h2>Autorizaci&oacute;n de transacci&oacute;n rechazada con Oneclick</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$transactionResult->details[0]->authorizationCode}<br>
                <b>Monto:</b> $ {$transactionResult->details[0]->amount}<br>
                <b>Order de Compra: </b> {$transactionResult->details[0]->buyOrder}<br>
                <b>Fecha de la Transacci&oacute;n: </b>".date('d-m-Y', strtotime($transactionResult->transactionDate)).'<br>
                <b>Hora de la Transacci&oacute;n: </b>'.date('H:i:s', strtotime($transactionResult->transactionDate))."<br>
                <b>Tarjeta: </b>**** **** **** {$transactionResult->cardNumber}<br>
            </p>";

            return $message;
        } else {
            if ($transactionResult->details[0]->status == 'ERROR') {
                $error = $transactionResult->details[0]->status;
                $detail = isset($transactionResult->details[0]) ? $transactionResult->details[0] : 'Sin detalles';
                $message = "<h2>Transacci&oacute;n fallida con Oneclick</h2>
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
}
