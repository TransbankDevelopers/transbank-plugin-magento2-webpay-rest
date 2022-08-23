<?php

namespace Transbank\Webpay\Controller\Transaction;

use Transbank\Webpay\Model\LogHandler;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\OneclickInscriptionData;

/**
 * Controller for create Oneclick Inscription.
 */
class CreateOneclick extends \Magento\Framework\App\Action\Action
{
    protected $configProvider;

    /**
     * CreateOneclick constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Checkout\Model\Cart                     $cart
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Quote\Model\QuoteManagement             $quoteManagement
     * @param \Magento\Store\Model\StoreManagerInterface       $storeManager
     * @param \Transbank\Webpay\Model\Config\ConfigProvider    $configProvider
     * @param \Transbank\Webpay\Model\OneclickInscriptionDataFactory   $OneclickInscriptionDataFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $OneclickInscriptionDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->OneclickInscriptionDataFactory = $OneclickInscriptionDataFactory;
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
        $order = null;
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $orderStatusPendingPayment = $this->configProvider->getOneclickOrderPendingStatus();

        try {
            $guestEmail = isset($_GET['guestEmail']) ? $_GET['guestEmail'] : null;

            $config = $this->configProvider->getPluginConfigOneclick();

            $tmpOrder = $this->getOrder();
            $this->checkoutSession->restoreQuote();

            $quote = $this->cart->getQuote();

            if ($guestEmail != null) {
                $this->setQuoteData($quote, $guestEmail);
            }

            $quote->getPayment()->importData(['method' => Oneclick::CODE]);
            $quote->collectTotals();
            $order = $tmpOrder;
            if ($tmpOrder != null && $tmpOrder->getStatus() == $orderStatusCanceled) {
                $order = $this->quoteManagement->submit($quote);
            }
            $grandTotal = round($order->getGrandTotal());

            $this->checkoutSession->setLastQuoteId($quote->getId());
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastOrderStatus($order->getStatus());
            $this->checkoutSession->setGrandTotal($grandTotal);

            $baseUrl = $this->storeManager->getStore()->getBaseUrl();

            $returnUrl = $baseUrl.$config['URL_RETURN'];
            $quoteId = $quote->getId();
            $orderId = $this->getOrderId();

            $customerId = 'U_'.$order->getCustomerId();

            $quote->save();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
            $response = $transbankSdkWebpay->createInscription($customerId, $order->getCustomerEmail(), $returnUrl);
            $dataLog = ['customerId' => $customerId, 'orderId' => $orderId];
            $message = '<h3>Esperando Inscripción con Oneclick</h3><br>'.json_encode($dataLog);

            if (isset($response['token']) && isset($response['urlWebpay'])) {
                $oneclickInscriptionData = $this->saveOneclickInscriptionData(
                    OneclickInscriptionData::PAYMENT_STATUS_WATING,     // status
                    $response['token'],             // token
                    $customerId,                    // username
                    $order->getCustomerEmail(),     // email
                    $order->getCustomerId(),        // user_id
                    $this->getOrderId(),            // order_id
                    $config['ENVIRONMENT'],         // environment
                    $config['COMMERCE_CODE']        // commerce_code
                );
                $order->setStatus($orderStatusPendingPayment);
            } else {
                $oneclickInscriptionData = $this->saveOneclickInscriptionData(
                    OneclickInscriptionData::PAYMENT_STATUS_FAILED,
                    $response['token'],             // token
                    '',                             // username
                    $order->getCustomerEmail(),     // email
                    $order->getCustomerId(),        // user_id
                    $this->getOrderId(),            // order_id
                    $config['ENVIRONMENT'],         // environment
                    $config['COMMERCE_CODE']        // commerce_code
                );
                $order->cancel();
                $order->save();
                $order->setStatus($orderStatusCanceled); // Debería de cancelar la orden?
                $message = '<h3>Error en Inscripción con Oneclick</h3><br>'.json_encode($response);
            }

            $order->addStatusToHistory($order->getStatus(), $message);
            $order->save();

            $this->checkoutSession->getQuote()->setIsActive(true)->save();
            $this->cart->getQuote()->setIsActive(true)->save();
        } catch (\Exception $e) {
            $message = 'Error al crear transacción: '.$e->getMessage();
            $this->log->logError($message);
            $response = ['error' => $message];
            if ($order != null) {
                $order->cancel();
                $order->save();
                $order->setStatus($orderStatusCanceled);
                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();
            }
        }

        $result = $this->resultJsonFactory->create();
        $result->setData($response);

        return $result;
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
     * @param $token_ws
     * @param $payment_status
     * @param $order_id
     * @param $quote_id
     *
     * @throws \Exception
     *
     * @return OneclickInscriptionData
     */
    protected function saveOneclickInscriptionData( // Copiar esta funcion para guardar los datos en CommitOneClick.php
        $status,
        $token,
        $username,
        $email,
        $user_id,
        $order_id,
        $environment,
        $commerce_code
    )
    {
        $oneclickInscriptionData = $this->OneclickInscriptionDataFactory->create();
        $oneclickInscriptionData->setData([
            'status'          => $status,
            'token'          => $token,
            'username'       => $username,
            'email'          => $email,
            'user_id'        => $user_id,
            'order_id'       => $order_id,
            'environment'    => $environment,
            'commerce_code'  => $commerce_code,
            'metadata'       => json_encode($this->checkoutSession->getData()),
        ]);
        $oneclickInscriptionData->save();

        return $oneclickInscriptionData;
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
}
