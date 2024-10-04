<?php

namespace Transbank\Webpay\Controller\Transaction;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Transbank\Webpay\Model\Oneclick;
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Helper\TbkResponseHelper;
use Transbank\Webpay\Helper\ObjectManagerHelper;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\Redirect;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderDataFactory;
use Transbank\Webpay\Model\WebpayOrderDataRepository;
use Magento\Sales\Model\Order\Payment\Transaction;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Magento\Framework\View\Result\PageFactory;
use Transbank\Webpay\Helper\QuoteHelper;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException;
use Transbank\Webpay\Exceptions\InvalidRequestException;
use Transbank\Webpay\Exceptions\MissingArgumentException;
use Transbank\Webpay\Model\WebpayOrderData;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Controller for create Oneclick Inscription.
 */
class AuthorizeOneclick extends Action
{
    const ONECLICK_EXCEPTION_FLOW_MESSAGE = 'No se pudo procesar el pago.';
    const AUTHORIZED_RESPONSE_CODE = 0;
    protected $configProvider;

    private $cart;
    private $checkoutSession;
    private $oneclickInscriptionDataFactory;
    private $log;
    private $webpayOrderDataFactory;
    protected $webpayOrderDataRepository;
    private $resultPageFactory;
    protected $eventManager;
    protected $messageManager;
    private $oneclickConfig;
    private $quoteHelper;
    protected $customerSession;

    /**
     * AuthorizeOneclick constructor.
     *
     * @param Context $context
     * @param Cart $cart
     * @param Session $checkoutSession
     * @param PageFactory $resultPageFactory
     * @param ConfigProvider $configProvider
     * @param EventManagerInterface $eventManager
     * @param OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
     * @param WebpayOrderDataFactory $webpayOrderDataFactory
     * @param WebpayOrderDataRepository $webpayOrderDataRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Cart $cart,
        Session $checkoutSession,
        PageFactory $resultPageFactory,
        ConfigProvider $configProvider,
        EventManagerInterface $eventManager,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        WebpayOrderDataFactory $webpayOrderDataFactory,
        WebpayOrderDataRepository $webpayOrderDataRepository,
        ManagerInterface $messageManager,
        QuoteHelper $quoteHelper,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->configProvider = $configProvider;
        $this->messageManager = $messageManager;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->webpayOrderDataRepository = $webpayOrderDataRepository;
        $this->resultPageFactory = $resultPageFactory;
        $this->eventManager = $eventManager;
        $this->log = new PluginLogger();
        $this->oneclickConfig = $configProvider->getPluginConfigOneclick();
        $this->quoteHelper = $quoteHelper;
        $this->customerSession = $customerSession;
    }

    /**
     * This method handle the controller request.
     *
     * @throws InvalidRequestException When inscription is not defined.
     *
     * @return Page|Redirect The result of handling the request.
     */
    public function execute()
    {
        try {
            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $request = $requestMethod === 'POST' ? $_POST : $_GET;

            $this->log->logInfo('Autorizando transacción Oneclick.');
            $this->log->logInfo('Request: method -> ' . $requestMethod);
            $this->log->logInfo('Request: payload -> ' . json_encode($request));

            if (!isset($_POST['inscription'])) {
                throw new InvalidRequestException('Falta el campo inscription');
            }

            $inscriptionId = intval($request['inscription']);

            return $this->handleOneclickRequest($inscriptionId);
        } catch (InvalidRequestException | MissingArgumentException | MallTransactionAuthorizeException | GuzzleException $e) {
            return $this->handleException($e);
        }
    }

    /**
     * This method handle Oneclick Request
     *
     * @param int $inscriptionId The Id for the inscription.
     *
     * @throws InvalidRequestException When the user is not logged in or when the user pays with a card that is not registered
     *
     * @return Page|Redirect The result of handling Oneclick the request.
     */
    private function handleOneclickRequest(int $inscriptionId)
    {
        $this->checkoutSession->restoreQuote();
        $quote = $this->cart->getQuote();
        $quote->getPayment()->importData(['method' => Oneclick::CODE]);
        $quote->collectTotals();
        $quote->save();

        $order = $this->getOrder($this->checkoutSession->getLastOrderId());
        $orderId = $order->getId();
        $grandTotal = round($order->getGrandTotal());

        if ($this->checkTransactionIsAlreadyProcessed($orderId, $quote->getId())) {
            return $this->handleTransactionAlreadyProcessed($orderId, $quote->getId());
        }

        if (!$this->isCustomerLoggedIn()) {
            throw new InvalidRequestException("No se ha iniciado sesión de usuario.");
        }

        $inscription = $this->getOneclickInscriptionData($inscriptionId);

        if (!$this->validatePayerMatchesCardInscription($inscription)) {
            throw new InvalidRequestException("Datos incorrectos para autorizar la transacción.");
        }

        $transbankSdkWebpay = new TransbankSdkWebpayRest($this->oneclickConfig);

        $username = $inscription->getUsername();
        $tbkUser = $inscription->getTbkUser();

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

        if (
            isset($authorizeResponse->details) &&
            $authorizeResponse->details[0]->responseCode == self::AUTHORIZED_RESPONSE_CODE
        ) {
            return $this->handleAuthorizedTransaction($order, $authorizeResponse, $grandTotal);
        } else {
            return $this->handleUnauthorizedTransaction($order, $authorizeResponse, $grandTotal);
        }
    }

    /**
     * This method handle de authorized transaction flow.
     *
     * @param Order                            $order             The Magento order.
     * @param MallTransactionAuthorizeResponse $authorizeResponse The Oneclick authorization response.
     * @param float                            $totalAmount       The total amount of the order.
     *
     * @return Page The success result page.
     */
    private function handleAuthorizedTransaction(
        Order $order,
        MallTransactionAuthorizeResponse $authorizeResponse,
        float $totalAmount
    ): Page {
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

        $this->checkoutSession->getQuote()->setIsActive(false)->save();

        return $this->redirectToSuccess($responseData);
    }


    /**
     * This method handle de unauthorized transaction flow.
     *
     * @param Order                            $order             The Magento order.
     * @param MallTransactionAuthorizeResponse $authorizeResponse The Oneclick authorization response.
     * @param float                            $totalAmount       The total amount of the order.
     *
     * @return Redirect Redirect to cart page.
     */
    private function handleUnauthorizedTransaction(
        Order $order,
        MallTransactionAuthorizeResponse $authorizeResponse,
        float $totalAmount
    ): Redirect {
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

    /**
     * This method handle the flow for orders already processed.
     *
     * @param int $orderId The order id.
     * @param int $quoteId The quote id.
     *
     * @return Page|Redirect The result of handling Oneclick the request.
     */
    private function handleTransactionAlreadyProcessed(int $orderId, int $quoteId)
    {
        $webpayOrderData = $this->getWebpayOrderDataByOrderIdAndQuoteId($orderId, $quoteId);
        $status = $webpayOrderData->getPaymentStatus();

        if ($status == WebpayOrderData::PAYMENT_STATUS_SUCCESS) {
            $metadata = $webpayOrderData->getMetadata();
            $response = json_decode($metadata);
            $formattedResponse = TbkResponseHelper::getOneclickFormattedResponse($response);

            return $this->redirectToSuccess($formattedResponse);
        }

        return $this->redirectWithErrorMessage("La orden ya fue procesada.");
    }

    /**
     * This method will handle the flow when an exception is thrown.
     *
     * @param Exception $exception The exception object.
     *
     * @return Redirect Redirect to cart page.
     */
    private function handleException(Exception $exception): Redirect
    {
        $message = self::ONECLICK_EXCEPTION_FLOW_MESSAGE;
        $exceptionName = get_class($exception);

        $this->log->logError('Error al procesar el pago: ');
        $this->log->logError($exceptionName . ": " .$exception->getMessage());
        $this->log->logError($exception->getTraceAsString());

        $order = $this->checkoutSession->getLastRealOrder();

        if ($order->getId()) {
            $this->cancelOrder($order, $message);
            $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());
        }

        return $this->redirectWithErrorMessage($message);
    }

    /**
     * This method show the success result page.
     *
     * @param array $responseData The formatted response.
     *
     * @return Page The success result page.
     */
    private function redirectToSuccess(array $responseData): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->addHandle('transbank_checkout_success');
        $block = $resultPage->getLayout()->getBlock('transbank_success');
        $block->setResponse($responseData);
        return $resultPage;
    }

    /**
     * This method redirect to the cart page.
     *
     * @param string $message The error message to show in the page.
     *
     * @return Redirect Redirect to cart page.
     */
    private function redirectWithErrorMessage(string $message): Redirect
    {
        $this->messageManager->addErrorMessage(__($message));
        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }


    /**
     * This method cancels the order and updates its status.
     *
     * @param Order $order The Magento order.
     * @param string $message The message to show in order resume.
     *
     * @return void
     */
    private function cancelOrder(Order $order, string $message): void
    {
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $order->cancel();
        $order->setStatus($orderStatusCanceled);
        $order->addStatusToHistory($order->getStatus(), $message);
        $order->save();
    }

    /**
     * This method check if the order already process.
     *
     * @param int $orderId The order id.
     * @param int $quoteId The quote id.
     *
     * @return bool True if the order is already processed, false if it is not processed.
     */
    private function checkTransactionIsAlreadyProcessed(int $orderId, int $quoteId): bool
    {
        $webpayOrderData = $this->getWebpayOrderDataByOrderIdAndQuoteId($orderId, $quoteId);
        $status = $webpayOrderData->getPaymentStatus();

        return $status == WebpayOrderData::PAYMENT_STATUS_SUCCESS ||
            $status == WebpayOrderData::PAYMENT_STATUS_NULLIFIED ||
            $status == WebpayOrderData::PAYMENT_STATUS_REVERSED;
    }

    /**
     * This method return the order object based on the id.
     *
     * @param int $orderId The order id.
     *
     * @return Order The Magento order.
     */
    private function getOrder(int $orderId): Order
    {
        /**
         * @var Order
         */
        $order = ObjectManagerHelper::get(Order::class);

        return $order->load($orderId);
    }

    /**
     * This method return the WebpayOrderData base on the order id and quote id.
     *
     * @param int $orderId The order id.
     * @param int $quoteId The quite id.
     *
     * @return WebpayOrderData The Webpay order data object.
     */
    private function getWebpayOrderDataByOrderIdAndQuoteId(int $orderId, int $quoteId): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->getByOrderIdAndQuoteId($orderId, $quoteId);
    }

    /**
     * This method return the OneclickInscriptionData based on the id.
     * @param $inscriptionId The OneclickInscriptionData id.
     *
     * @return OneclickInscriptionData The Oneclick inscription data object.
     */
    protected function getOneclickInscriptionData(int $inscriptionId): OneclickInscriptionData
    {
        $oneclickInscriptionDataModel = $this->oneclickInscriptionDataFactory->create();
        return $oneclickInscriptionDataModel->load($inscriptionId, 'id');
    }

    /**
     * Validate that the user paying for the order is the same as the one who registered the card.
     *
     * @param OneclickInscriptionData $inscriptionData The card inscription data.
     *
     * @return bool True if the payer matches the card inscription, false otherwise.
     */
    private function validatePayerMatchesCardInscription(OneclickInscriptionData $inscriptionData)
    {
        $customerData = $this->customerSession->getCustomerData();
        $customerId = $customerData->getId();
        $inscriptionUserId = $inscriptionData->getUserId();

        return $customerId == $inscriptionUserId;
    }

    /**
     * This method check if customer is logged in.
     *
     * @return bool True if customer is logged in, otherwise false.
     */
    private function isCustomerLoggedIn(): bool {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * This method update the WebpayOrderData.
     *
     * @param MallTransactionAuthorizeResponse $authorizeResponse The authorization response.
     * @param float $amount The amount of the order.
     * @param string $payment_status The payment status.
     * @param int $order_id The order id.
     * @param int $quote_id The quote id.
     *
     * @return WebpayOrderData
     */
    protected function saveOneclickData(
        MallTransactionAuthorizeResponse $authorizeResponse,
        float $amount,
        string $payment_status,
        int $order_id,
        int $quote_id
    ): void {
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
    }
}
