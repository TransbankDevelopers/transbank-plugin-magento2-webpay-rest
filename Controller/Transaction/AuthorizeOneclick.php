<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Transbank\Webpay\Model\Oneclick;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Helper\TbkResponseHelper;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\ResultInterface;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderDataFactory;
use Magento\Sales\Model\Order\Payment\Transaction;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Transbank\Webpay\Helper\QuoteHelper;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;


/**
 * Controller for create Oneclick Inscription.
 */
class AuthorizeOneclick extends Action
{
    protected $configProvider;

    private $cart;
    private $checkoutSession;
    private $resultJsonFactory;
    private $oneclickInscriptionDataFactory;
    private $log;
    private $webpayOrderDataFactory;
    private $resultPageFactory;
    protected $messageManager;
    private $oneclickConfig;
    private $quoteHelper;

    /**
     * AuthorizeOneclick constructor.
     *
     * @param Context $context
     * @param Cart $cart
     * @param Session $checkoutSession
     * @param JsonFactory $resultJsonFactory
     * @param ConfigProvider $configProvider
     * @param OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
     * @param WebpayOrderDataFactory $webpayOrderDataFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Cart $cart,
        Session $checkoutSession,
        JsonFactory $resultJsonFactory,
        PageFactory $resultPageFactory,
        ConfigProvider $configProvider,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        WebpayOrderDataFactory $webpayOrderDataFactory,
        ManagerInterface $messageManager,
        QuoteHelper $quoteHelper
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configProvider = $configProvider;
        $this->messageManager = $messageManager;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->log = new PluginLogger();
        $this->oneclickConfig = $configProvider->getPluginConfigOneclick();
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * @throws \Exception
     *
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $response = null;
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $orderStatusSuccess = $this->configProvider->getOneclickOrderSuccessStatus();
        $oneclickTitle = $this->configProvider->getOneclickTitle();

        try {
            $resultJson = $this->resultJsonFactory->create();

            if (isset($_POST['inscription'])) {
                $inscriptionId = intval($_POST['inscription']);
            } else {
                return $resultJson->setData(['status' => 'error', 'message' => 'Error autorizando transacción', 'flag' => 0]);
            }

            $inscription = $this->getOneclickInscriptionData($inscriptionId);
            $username = $inscription->getUsername();
            $tbkUser = $inscription->getTbkUser();

            $this->checkoutSession->restoreQuote();

            $quote = $this->cart->getQuote();

            $quote->getPayment()->importData(['method' => Oneclick::CODE]);
            $quote->collectTotals();
            $order = $this->getOrder();
            $grandTotal = round($order->getGrandTotal());

            $quoteId = $quote->getId();
            $orderId = $order->getId();

            $quote->save();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->oneclickConfig);

            $buyOrder = "100000" . $orderId;
            $childBuyOrder = "200000" . $orderId;

            $details = [
                [
                    "commerce_code" => $this->oneclickConfig['CHILD_COMMERCE_CODE'],
                    "buy_order" => $childBuyOrder,
                    "amount" => $grandTotal,
                    "installments_number" => 1
                ]
            ];

            $response = $transbankSdkWebpay->authorizeTransaction($username, $tbkUser, $buyOrder, $details);
            $dataLog = ['customerId' => $username, 'orderId' => $orderId];

            if (isset($response->details) && $response->details[0]->responseCode == 0) {

                $webpayOrderData = $this->saveWebpayData(
                    $response,
                    $grandTotal,
                    OneclickInscriptionData::PAYMENT_STATUS_SUCCESS,
                    $orderId,
                    $quoteId
                );


                $this->checkoutSession->setLastQuoteId($quote->getId());
                $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());
                $this->checkoutSession->setGrandTotal($grandTotal);
                $this->checkoutSession->getQuote()->setIsActive(true)->save();
                $this->cart->getQuote()->setIsActive(true)->save();

                $orderLogs = '<h3>Pago autorizado exitosamente con ' . $oneclickTitle . '</h3><br>' . json_encode($dataLog);
                $payment = $order->getPayment();

                $payment->setLastTransId($response->details[0]->authorizationCode);
                $payment->setTransactionId($response->details[0]->authorizationCode);
                $payment->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $response->details[0]]);

                $order->setState($orderStatusSuccess)->setStatus($orderStatusSuccess);
                $order->addStatusToHistory($order->getStatus(), $orderLogs);
                $order->save();

                $this->checkoutSession->getQuote()->setIsActive(false)->save();

                $formattedResponse = TbkResponseHelper::getOneclickFormattedResponse($response);

                $resultPage = $this->resultPageFactory->create();
                $resultPage->addHandle('transbank_checkout_success');
                $block = $resultPage->getLayout()->getBlock('transbank_success');
                $block->setResponse($formattedResponse);
                return $resultPage;
            } else {
                $webpayOrderData = $this->saveWebpayData(
                    $response,
                    $grandTotal,
                    OneclickInscriptionData::PAYMENT_STATUS_FAILED,
                    $orderId,
                    $quoteId,
                );

                $order->setStatus($orderStatusCanceled);
                $message = '<h3>Error en autorización con Oneclick Mall</h3><br>' . json_encode($response);

                $order->addStatusToHistory($order->getStatus(), $message);
                $order->cancel();
                $order->save();

                $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());

                $message = 'Tu transacción no pudo ser autorizada. Ningún cobro fue realizado.';
                $this->messageManager->addErrorMessage(__($message));

                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        } catch (\Exception $e) {
            $message = 'Error al crear transacción: ' . $e->getMessage();

            $this->log->logError($message);
            $response = ['error' => $message];

            if ($order != null) {
                $order->cancel();
                $order->setStatus($orderStatusCanceled);
                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();
                $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
    }

    /**
     * @return |null
     */
    private function getOrder()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId == null) {
                return null;
            }

            $objectManager = ObjectManager::getInstance();

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
     * @return OneclickInscriptionData
     */
    protected function getOneclickInscriptionData($inscriptionId): OneclickInscriptionData
    {
        $oneclickInscriptionDataModel = $this->oneclickInscriptionDataFactory->create();
        return $oneclickInscriptionDataModel->load($inscriptionId, 'id');
    }

    /**
     * @return string
     */
    protected function getOrderId()
    {
        return $this->checkoutSession->getLastRealOrderId();
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
    protected function saveWebpayData($authorizeResponse, $amount, $payment_status, $order_id, $quote_id)
    {
        $webpayOrderData = $this->webpayOrderDataFactory->create();
        $webpayOrderData->setData([
            'buy_order'       => $authorizeResponse->getBuyOrder(),
            'child_buy_order' => $authorizeResponse->getDetails()[0]->getBuyOrder(),
            'commerce_code'   => $this->oneclickConfig['COMMERCE_CODE'],
            'child_commerce_code'   => $authorizeResponse->getDetails()[0]->getCommerceCode(),
            'payment_status'  => $payment_status,
            'order_id'        => $order_id,
            'quote_id'        => $quote_id,
            'amount'          => $amount,
            'metadata'        => json_encode($authorizeResponse),
            'environment'     => $this->oneclickConfig['ENVIRONMENT'],
            'product'         => Oneclick::PRODUCT_NAME
        ]);
        $webpayOrderData->save();

        return $webpayOrderData;
    }
}
