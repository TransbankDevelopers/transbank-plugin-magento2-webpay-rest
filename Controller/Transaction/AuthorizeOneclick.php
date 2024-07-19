<?php

namespace Transbank\Webpay\Controller\Transaction;

use Exception;
use Magento\Sales\Model\Order;
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
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\Webpay\Exceptions\InvalidRequestException;

/**
 * Controller for create Oneclick Inscription.
 */
class AuthorizeOneclick extends Action
{
    const ONECLICK_EXCEPTION_FLOW_MESSAGE = 'No se pudo procesar el pago.';
    protected $configProvider;

    private $cart;
    private $checkoutSession;
    private $resultJsonFactory;
    private $oneclickInscriptionDataFactory;
    private $log;
    private $webpayOrderDataFactory;
    private $resultPageFactory;
    protected $eventManager;
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
        EventManagerInterface $eventManager,
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
        $this->eventManager = $eventManager;
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
        try {
            if (!isset($_POST['inscription'])) {
                throw new InvalidRequestException('Petición invalida: Falta el campo inscription');
            }

            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $request = $requestMethod === 'POST' ? $_POST : $_GET;

            $inscriptionId = intval($request['inscription']);

            $this->log->logInfo('Autorizando transacción Oneclick.');
            $this->log->logInfo('Request: method -> ' . $requestMethod);
            $this->log->logInfo('Request: payload -> ' . json_encode($request));

            return $this->handleOneclickRequest($inscriptionId);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function handleOneclickRequest(int $inscriptionId)
    {
        $inscription = $this->getOneclickInscriptionData($inscriptionId);
        $username = $inscription->getUsername();
        $tbkUser = $inscription->getTbkUser();

        $this->checkoutSession->restoreQuote();
        $quote = $this->cart->getQuote();
        $quote->getPayment()->importData(['method' => Oneclick::CODE]);
        $quote->collectTotals();
        $quote->save();

        $order = $this->getOrder();
        $orderId = $order->getId();
        $grandTotal = round($order->getGrandTotal());

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

        $authorizeResponse = $transbankSdkWebpay->authorizeTransaction($username, $tbkUser, $buyOrder, $details);

        if (isset($authorizeResponse->details) && $authorizeResponse->details[0]->responseCode == 0) {
            return $this->handleAuthorizedTransaction($order, $authorizeResponse, $grandTotal);
        } else {
            return $this->handleUnauthorizedTransaction($order, $authorizeResponse, $grandTotal);
        }
    }

    private function handleAuthorizedTransaction(
        Order $order,
        MallTransactionAuthorizeResponse $authorizeResponse,
        float $totalAmount
    ) {
        $quoteId = $order->getQuoteId();

        $this->saveOneclickData(
            $authorizeResponse,
            $totalAmount,
            OneclickInscriptionData::PAYMENT_STATUS_SUCCESS,
            $order->getId(),
            $quoteId
        );


        $this->checkoutSession->setLastQuoteId($quoteId);
        $this->checkoutSession->setLastSuccessQuoteId($quoteId);
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());
        $this->checkoutSession->setGrandTotal($totalAmount);
        $this->checkoutSession->getQuote()->setIsActive(true)->save();
        $this->cart->getQuote()->setIsActive(true)->save();

        $orderLogs = '<h3>Pago autorizado exitosamente con Oneclick Mall</h3><br>' . json_encode($authorizeResponse);
        $payment = $order->getPayment();

        $payment->setLastTransId($authorizeResponse->details[0]->authorizationCode);
        $payment->setTransactionId($authorizeResponse->details[0]->authorizationCode);
        $payment->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $authorizeResponse->details[0]]);

        $orderStatusSuccess = $this->configProvider->getOneclickOrderSuccessStatus();
        $order->setState($orderStatusSuccess)->setStatus($orderStatusSuccess);
        $order->addStatusToHistory($order->getStatus(), $orderLogs);
        $order->save();

        $this->eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            ['order' => $order]
        );

        $responseData = TbkResponseHelper::getOneclickFormattedResponse($authorizeResponse);

        return $this->redirectToSuccess($responseData);
    }

    private function handleUnauthorizedTransaction(
        Order $order,
        MallTransactionAuthorizeResponse $authorizeResponse,
        float $totalAmount
    ) {
        $this->saveOneclickData(
            $authorizeResponse,
            $totalAmount,
            OneclickInscriptionData::PAYMENT_STATUS_FAILED,
            $order->getId(),
            $order->getQuoteId()
        );

        $message = '<h3>Error en autorización con Oneclick Mall</h3><br>' . json_encode($authorizeResponse);

        $this->cancelOrder($order, $message);
        $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());

        $message = 'Tu transacción no pudo ser autorizada. Ningún cobro fue realizado.';
        return $this->redirectWithErrorMessage($message);
    }

    private function handleException(Exception $exception)
    {
        $message = self::ONECLICK_EXCEPTION_FLOW_MESSAGE;

        $this->log->logError('Error al procesar el pago: ');
        $this->log->logError($exception->getMessage());
        $this->log->logError($exception->getTraceAsString());

        $order = $this->checkoutSession->getLastRealOrder();

        if ($order->getId()) {
            $this->cancelOrder($order, $message);
            $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());
        }

        return $this->redirectWithErrorMessage($message);
    }

    private function redirectToSuccess(array $responseData)
    {
        $this->checkoutSession->getQuote()->setIsActive(false)->save();

        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('transbank_checkout_success');
        $block = $resultPage->getLayout()->getBlock('transbank_success');
        $block->setResponse($responseData);
        return $resultPage;
    }

    private function redirectWithErrorMessage(string $message)
    {
        $this->messageManager->addErrorMessage(__($message));
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    private function cancelOrder(Order $order, string $message)
    {
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $order->cancel();
        $order->setStatus($orderStatusCanceled);
        $order->addStatusToHistory($order->getStatus(), $message);
        $order->save();
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
    protected function saveOneclickData($authorizeResponse, $amount, $payment_status, $order_id, $quote_id)
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
