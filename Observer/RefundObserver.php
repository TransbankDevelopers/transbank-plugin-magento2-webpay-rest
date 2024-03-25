<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Model\Webpay;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderDataFactory;
use Transbank\Webpay\Model\LogHandler;
use Transbank\webpay\Model\Config\ConfigProvider;

class RefundObserver implements ObserverInterface
{

    protected $logger;
    protected $configProvider;
    protected $webpayOrderDataFactory;

    public function __construct (
        ConfigProvider $configProvider,
        WebpayOrderDataFactory $webpayOrderDataFactory
    )
    {
        $this->logger = new LogHandler();
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $creditMemo = $observer->getEvent()->getCreditmemo();
        $order = $creditMemo->getOrder();
        $grandTotal = $creditMemo->getGrandTotal();
        $paymentMethod = $order->getPayment()->getMethod();

        if (!$this->shouldProcessRefund($paymentMethod)) {
            return;
        }

        try {
            $this->logger->logInfo('Realizando reembolso. Orden: ' . $order->getId() . ' Monto: ' . $grandTotal);
            $productConfig = $this->getProductConfig($paymentMethod);
            $transbankSdk = new TransbankSdkWebpayRest($productConfig);
            $transactionData = $this->getTransactionData($paymentMethod, $order);
            $refundResponse = $this->refundTransaction($paymentMethod, $transbankSdk, $transactionData, $grandTotal);
            $refundType = $refundResponse->getType();
            if ($refundType === 'REVERSED' ||
                ($refundType === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0) {
                    $this->logger->logInfo('Rembolso realizado correctamente en Transbank');
                    $transactionData['webpayOrderData']->setMetadata(json_encode($refundResponse). ' ' .
                        $transactionData['metadata']);
                    $transactionData['webpayOrderData']->setPaymentStatus($refundType);
                    $transactionData['webpayOrderData']->save();
                    $refundComment = $this->createHistoryComment($refundType, $refundResponse, $grandTotal);
                    $order->addStatusHistoryComment($refundComment);
                    $order->save();
                    return;
            }
            $errorMessage = 'Error en el reembolso. Código de respuesta Transbank: ' .
                $refundResponse->getResponseCode();
            $order->addStatusHistoryComment($errorMessage);
            $order->save();
            $this->logger->logError('Error en el reembolso. Código de respuesta Transbank: ' .
                $refundResponse->getResponseCode());
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMessage));

        }
        catch (\Exception $exception) {
            $errorMessage = "Error en el reembolso: " . $exception->getMessage();
            $order->addStatusHistoryComment($errorMessage);
            $order->save();
            $this->logger->logError($errorMessage);
            throw new \Magento\Framework\Exception\LocalizedException(__($errorMessage));
        }

    }

    /**
     * @param string $paymentMethod
     *
     * @return bool
     */
    private function shouldProcessRefund($paymentMethod): bool {
        return $paymentMethod == Webpay::CODE || $paymentMethod == OneClick::CODE;
    }

    /**
     * @param string $paymentMethod
     *
     * @return array
     */
    private function getProductConfig($paymentMethod): array {
        if ($paymentMethod == Webpay::CODE) {
            return $this->configProvider->getPluginConfig();
        }
        return $this->configProvider->getPluginConfigOneclick();

    }

    /**
     * @param string $paymentMethod
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array
     */
    private function getTransactionData(string $paymentMethod, \Magento\Sales\Model\Order $order): array {
        $transactionData = [];
        $webpayOrderDataModel = $this->webpayOrderDataFactory->create();
        $webpayOrderData = null;
        if ($paymentMethod == Webpay::CODE) {
            $webpayOrderData = $webpayOrderDataModel->load($order->getIncrementId(), 'order_id');
            $transactionData['token'] = $webpayOrderData->getToken();
        }
        else {
            $webpayOrderData = $webpayOrderDataModel->load($order->getId(), 'order_id');
            $transactionData['buyOrder'] = $webpayOrderData->getBuyOrder();
            $transactionData['childBuyOrder'] = $webpayOrderData->getChildBuyOrder();
            $transactionData['childCommerceCode'] = $webpayOrderData->getChildCommerceCode();
        }
        $transactionData['metadata'] = $webpayOrderData->getMetadata();
        $transactionData['webpayOrderData'] = $webpayOrderData;

        return $transactionData;
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
        int $amount) {
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
    private function createHistoryComment(string $refundType, object $refundResponse, int $amount): string {

        $type = $refundType == 'REVERSED' ? 'REVERSA' : 'ANULACIÓN';
        $message = 'Tipo: ' . $type . '<br>' .
            'Monto: $' . $amount;

        if ($refundType == 'NULLIFIED'){
            $message .= '<br>
                Saldo: $' . $refundResponse->getBalance() . '<br>
                Fecha: ' . $refundResponse->getAuthorizationDate() . '<br>
                Cod. autorización: ' . $refundResponse->getAuthorizationCode() . '<br>
                Cod. respuesta: ' . $refundResponse->getResponseCode();
        }

        return $message;
    }

}
