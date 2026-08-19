<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Order;
use Transbank\Webpay\Exceptions\InvalidRequestException;
use Transbank\Webpay\Exceptions\OrderNotFoundException;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\Service\OrderService;
use Transbank\Webpay\Model\Service\QuoteService;
use Transbank\Webpay\Helper\PluginLogger;

/**
 * Controller for create Oneclick Inscription.
 */
class CreateOneclick extends \Magento\Framework\App\Action\Action
{
    protected $configProvider;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $quoteManagement;
    protected $storeManager;
    protected $oneclickInscriptionDataFactory;
    protected $log;
    protected $oneclickInscriptionService;
    protected $orderService;
    protected $quoteService;
    protected $oneClickConfig;
    protected $customerSession;

    /**
     * CreateOneclick constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Checkout\Model\Session                  $checkoutSession
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Quote\Model\QuoteManagement             $quoteManagement
     * @param \Magento\Store\Model\StoreManagerInterface       $storeManager
     * @param \Transbank\Webpay\Model\Config\ConfigProvider    $configProvider
     * @param OneclickInscriptionDataFactory                   $oneclickInscriptionDataFactory
     * @param OneclickInscriptionService                       $oneclickInscriptionService
     * @param OrderService                                     $orderService
     * @param QuoteService                                     $quoteService
     * @param CustomerSession                                  $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        OneclickInscriptionService $oneclickInscriptionService,
        OrderService $orderService,
        QuoteService $quoteService,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->log = new PluginLogger();
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->orderService = $orderService;
        $this->quoteService = $quoteService;
        $this->customerSession = $customerSession;
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
            if (!$this->customerSession->isLoggedIn()) {
                throw new InvalidRequestException("No se ha iniciado sesión de usuario.");
            }

            $guestEmail = isset($_GET['guestEmail']) ? $_GET['guestEmail'] : null;

            $this->oneClickConfig = $this->configProvider->getPluginConfigOneclick();
            $oneclickTitle = $this->oneClickConfig['title'];

            $tmpOrder = $this->getOrder();
            $this->checkoutSession->restoreQuote();

            $quote = $this->quoteService->getCurrentQuote();

            if ($guestEmail != null) {
                $this->setQuoteData($quote, $guestEmail);
            }

            $this->quoteService->setPaymentMethod($quote, Oneclick::CODE);
            $order = $tmpOrder;
            if ($tmpOrder != null && $this->orderService->isCanceled($tmpOrder, $orderStatusCanceled)) {
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

            $returnUrl = $baseUrl . $this->oneClickConfig['URL_RETURN'];
            $orderId = $this->getOrderId();

            $customerId = (int) $this->customerSession->getCustomerId();
            $username = $this->oneclickInscriptionService->generateInscriptionUsername($customerId);
            $this->log->logInfo('New username: ' . json_encode($username));

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->oneClickConfig);
            $response = $transbankSdkWebpay->createInscription($username, $order->getCustomerEmail(), $returnUrl);
            $dataLog = ['customerId' => $username, 'orderId' => $orderId];
            $message = "<h3>Esperando Inscripción con {$oneclickTitle}</h3><br>" . json_encode($dataLog);

            if (isset($response['token']) && isset($response['urlWebpay'])) {
                $this->saveOneclickInscriptionData(
                    OneclickInscriptionData::PAYMENT_STATUS_WATING,
                    $response['token'],
                    $username,
                    $order->getCustomerEmail(),
                    $customerId,
                    $this->getOrderId(),
                );
                $this->orderService->setStatus($order, $orderStatusPendingPayment, $message);
            } else {
                $this->saveOneclickInscriptionData(
                    OneclickInscriptionData::PAYMENT_STATUS_FAILED,
                    $response['token'] ?? null,
                    $username,
                    $order->getCustomerEmail(),
                    $customerId,
                    $this->getOrderId(),
                );
                $message = '<h3>Error en Inscripción con {$oneclickTitle}</h3><br>' . json_encode($response);
                $this->orderService->cancel($order, $orderStatusCanceled, $message);
            }

            $this->quoteService->activate($this->quoteService->getCurrentQuote());
        } catch (\Exception $e) {
            $message = 'Error al crear transacción: ' . $e->getMessage();
            $this->log->logError($message);
            $response = ['error' => $message];
            if ($order != null) {
                $this->orderService->cancel($order, $orderStatusCanceled, $message);
            }
        }

        $result = $this->resultJsonFactory->create();
        $result->setData($response);

        return $result;
    }

    /**
     * @throws OrderNotFoundException When the session's last order id does not match an existing order.
     *
     * @return Order|null
     */
    protected function getOrder()
    {
        $orderId = $this->checkoutSession->getLastOrderId();

        if ($orderId == null) {
            return null;
        }

        return $this->orderService->getById($orderId);
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
    protected function saveOneclickInscriptionData(
        $status,
        $token,
        $username,
        $email,
        $user_id,
        $order_id
    ) {
        $oneclickInscriptionData = $this->oneclickInscriptionDataFactory->create();
        $oneclickInscriptionData->setData([
            'status'          => $status,
            'token'          => $token,
            'username'       => $username,
            'email'          => $email,
            'user_id'        => $user_id,
            'order_id'       => $order_id,
            'environment'    => $this->oneClickConfig['ENVIRONMENT'],
            'commerce_code'  => $this->oneClickConfig['COMMERCE_CODE'],
            'metadata'       => json_encode($this->checkoutSession->getData()),
        ]);
        $this->oneclickInscriptionService->save($oneclickInscriptionData);
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
