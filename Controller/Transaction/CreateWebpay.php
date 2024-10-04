<?php

namespace Transbank\Webpay\Controller\Transaction;

use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Webpay;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Helper\PluginLogger;

/**
 * Controller for create transaction Webpay.
 */
class CreateWebpay extends \Magento\Framework\App\Action\Action
{
    protected $configProvider;
    protected $cart;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $quoteManagement;
    protected $storeManager;
    protected $webpayOrderDataFactory;
    protected $log;
    protected $quoteRepository;
    protected $webpayConfig;

    /**
     * CreateWebpayM22 constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Checkout\Model\Cart                     $cart
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Quote\Model\QuoteManagement             $quoteManagement
     * @param \Magento\Store\Model\StoreManagerInterface       $storeManager
     * @param \Transbank\Webpay\Model\Config\ConfigProvider      $configProvider
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
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->log = new PluginLogger();
        $this->webpayConfig = $configProvider->getPluginConfig();
    }

    /**
     * @throws \Exception
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $this->log->logInfo('B.1. Iniciando medio de pago Webpay Plus');

        $response = null;
        $order = null;
        $orderStatusCanceled = $this->configProvider->getOrderErrorStatus();
        $orderStatusPendingPayment = $this->configProvider->getOrderPendingStatus();

        try {
            $guestEmail = isset($_GET['guestEmail']) ? $_GET['guestEmail'] : null;

            $tmpOrder = $this->getOrder();

            $this->checkoutSession->restoreQuote();
            $quote = $this->checkoutSession->getQuote();

            if ($guestEmail != null) {
                $this->setQuoteData($quote, $guestEmail);
            }

            $quote->getPayment()->importData(['method' => Webpay::CODE]);
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

            $returnUrl = $baseUrl . $this->webpayConfig['URL_RETURN'];
            $quoteId = $quote->getId();
            $orderId = $this->getOrderId();

            $quote->save();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->webpayConfig);
            $this->log->logInfo('B.2. Preparando datos antes de crear la transacción en Transbank');
            $this->log->logInfo('amount: ' . $grandTotal . ', sessionId: ' . $quoteId . ', buyOrder: ' . $orderId . ', returnUrl: ' . $returnUrl);
            $response = $transbankSdkWebpay->createTransaction($grandTotal, $quoteId, $orderId, $returnUrl);

            $dataLog = ['grandTotal' => $grandTotal, 'quoteId' => $quoteId, 'orderId' => $orderId];
            $message = '<h3>Esperando pago con Webpay</h3><br>' . json_encode($dataLog);

            if (isset($response['token_ws'])) {
                $this->saveWebpayData(
                    $response['token_ws'],
                    WebpayOrderData::PAYMENT_STATUS_WATING,
                    $orderId,
                    $quoteId
                );

                $this->log->logInfo('B.3. Transacción creada en Transbank');
                $this->log->logInfo(json_encode($response));

                $order->setStatus($orderStatusPendingPayment);
            } else {
                $this->saveWebpayData('', WebpayOrderData::PAYMENT_STATUS_ERROR, $orderId, $quoteId);
                $order->cancel();
                $order->save();
                $order->setStatus($orderStatusCanceled);
                $message = '<h3>Error en pago con Webpay</h3><br>' . json_encode($response);
                $this->log->logError('B.3. Transacción creada con error en Transbank');
                $this->log->logError(json_encode($response));
            }

            $order->addStatusToHistory($order->getStatus(), $message);
            $order->save();
        } catch (\Exception $e) {
            $message = 'Error al crear transacción: ' . $e->getMessage();
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
     */
    protected function saveWebpayData($token_ws, $payment_status, $order_id, $quote_id)
    {
        $webpayOrderData = $this->webpayOrderDataFactory->create();
        $webpayOrderData->setData([
            'token'          => $token_ws,
            'payment_status' => $payment_status,
            'order_id'       => $order_id,
            'quote_id'       => $quote_id,
            'metadata'       => json_encode($this->checkoutSession->getData()),
            'commerce_code'   => $this->webpayConfig['COMMERCE_CODE'],
            'environment'     => $this->webpayConfig['ENVIRONMENT'],
            'product'         => Webpay::PRODUCT_NAME
        ]);
        $webpayOrderData->save();
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
