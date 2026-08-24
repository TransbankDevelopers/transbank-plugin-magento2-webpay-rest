<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Transbank\Webpay\Model\Webpay;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Service\WebpayOrderDataService;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Transbank\Webpay\Helper\TbkResponseHelper;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Exceptions\EcommerceException;
use Magento\Framework\Message\ManagerInterface;
use Transbank\Webpay\Observer\Util\ObserverGuard;

class RefundObserver implements ObserverInterface
{

    protected $logger;
    protected $configProvider;
    protected $webpayOrderDataService;
    protected $orderRepository;
    protected $messageManager;

    private ObserverGuard $observerGuard;

    public function __construct(
        ConfigProvider $configProvider,
        WebpayOrderDataService $webpayOrderDataService,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        ObserverGuard $observerGuard
    ) {
        $this->logger = new PluginLogger();
        $this->configProvider = $configProvider;
        $this->webpayOrderDataService = $webpayOrderDataService;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->observerGuard = $observerGuard;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $errorMessageBase = 'Ocurrió un error al realizar la anulación en Webpay. ';
            $refundInstructions = 'Intente realizar la anulación mediante su portal privado de Transbank. Para mayor información del error revise los logs de la transacción.';
            $creditMemo = $observer->getEvent()->getCreditmemo();
            $grandTotal = $creditMemo->getGrandTotal();
            $order = $this->observerGuard->getOrderFromObserverOrSession($observer);

            if (!$order) {
                return;
            }

            if (!$this->observerGuard->isTransbankPayment($order)) {
                return;
            }

            $this->logger->logInfo('Realizando reembolso. Orden: ' . $order->getId() . ' Monto: ' . $grandTotal);
            $paymentMethod = $order->getPayment()->getMethod();
            $transbankSdk = new TransbankSdkWebpayRest($this->configProvider);
            $transactionData = $this->getTransactionData($paymentMethod, $order);
            $refundResponse = $this->refundTransaction(
                $paymentMethod,
                $transbankSdk,
                $transactionData,
                $grandTotal
            );
            $refundType = $refundResponse->getType();
            if (
                $refundType === 'REVERSED' ||
                ($refundType === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0
            ) {
                $this->logger->logInfo('Rembolso realizado correctamente en Transbank');
                $this->webpayOrderDataService->update($transactionData['webpayOrderData'], [
                    'metadata' => json_encode($refundResponse) . ' ' . $transactionData['metadata'],
                    'payment_status' => $refundType,
                ]);
                $refundComment = $this->createHistoryComment(
                    $refundType,
                    $refundResponse,
                    $grandTotal
                );
                $order->addStatusHistoryComment($refundComment);
                $this->orderRepository->save($order);
                return;
            }
            $errorMessage = $errorMessageBase . 'Código de respuesta Transbank: ' .
                $refundResponse->getResponseCode() . '. ' . $refundInstructions;
            $this->logger->logError($errorMessage);
            $order->addStatusHistoryComment($errorMessage);
            $this->orderRepository->save($order);
            $this->messageManager->addErrorMessage($errorMessageBase . $refundInstructions);
            return;
        } catch (\Exception $exception) {
            $errorMessage = $errorMessageBase . $exception->getMessage() . '. ' . $refundInstructions;
            $this->logger->logError($errorMessage);
            $order->addStatusHistoryComment($errorMessage);
            $this->orderRepository->save($order);
            $this->messageManager->addErrorMessage($errorMessageBase . $refundInstructions);
        }
    }

    /**
     * @param string $paymentMethod
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     */
    private function getTransactionData(string $paymentMethod, \Magento\Sales\Model\Order $order): array
    {
        $transactionData = [];
        $webpayOrderData = $this->getTransaction($paymentMethod, $order);
        $transactionData['metadata'] = $webpayOrderData->getMetadata();
        $transactionData['webpayOrderData'] = $webpayOrderData;
        if ($paymentMethod == Webpay::CODE) {
            $transactionData['token'] = $webpayOrderData->getToken();
        } else {
            $transactionData['buyOrder'] = $webpayOrderData->getBuyOrder();
            $transactionData['childBuyOrder'] = $webpayOrderData->getChildBuyOrder();
            $transactionData['childCommerceCode'] = $webpayOrderData->getChildCommerceCode();
        }
        return $transactionData;
    }

    /**
     * @param string $paymentMethod
     * @param \Magento\Sales\Model\Order $order
     *
     * @return \Transbank\Webpay\Model\WebpayOrderData
     * @throws EcommerceException
     */
    private function getTransaction(string $paymentMethod, \Magento\Sales\Model\Order $order)
    {
        $webpayOrderData = $this->webpayOrderDataService->getByOrderId($order->getId());

        if ($paymentMethod == Webpay::CODE && !$webpayOrderData) {
            $webpayOrderData = $this->webpayOrderDataService->getByOrderId($order->getIncrementId());
        }

        if (!$webpayOrderData) {
            throw new EcommerceException(
                'No se encontró la transacción con el número de orden: ' . $order->getIncrementId()
            );
        }

        return $webpayOrderData;
    }

    /**
     * @param string $paymentMethod
     * @param TransbankSdkWebpayRest $transbankSdk
     * @param array $transactionData
     * @param int $amount
     *
     * @return
     */
    private function refundTransaction(
        string $paymentMethod,
        TransbankSdkWebpayRest $transbankSdk,
        array $transactionData,
        int $amount
    ) {
        if ($paymentMethod == Webpay::CODE) {
            return $transbankSdk->refundWebpayPlusTransaction($transactionData['token'], $amount);
        }

        return $transbankSdk->refundOneClickTransaction(
            $transactionData['buyOrder'],
            $transactionData['childCommerceCode'],
            $transactionData['childBuyOrder'],
            $amount
        );
    }

    /**
     * @param string $refundType
     * @param object $refundResponse
     * @param int $amount
     *
     * @return string
     */
    private function createHistoryComment(string $refundType, object $refundResponse, int $amount): string
    {

        $type = $refundType == 'REVERSED' ? 'REVERSA' : 'ANULACIÓN';
        $message = '<strong>Reembolso exitoso</strong><br><br>' .
            '<strong>Tipo</strong>: ' . $type . '<br>' .
            '<strong>Monto</strong>: $' . $amount;

        if ($refundType == 'NULLIFIED') {
            $transactionLocalDate = TbkResponseHelper::utcToLocalDate($refundResponse->getAuthorizationDate());
            $message .= '<br>
                <strong>Saldo</strong>: $' . $refundResponse->getBalance() . '<br>
                <strong>Fecha</strong>: ' . $transactionLocalDate . '<br>
                <strong>Código autorización</strong>: ' . $refundResponse->getAuthorizationCode() . '<br>
                <strong>Código respuesta</strong>: ' . $refundResponse->getResponseCode();
        }

        return $message;
    }
}
