<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;

class ReverseOneclickOrder implements ObserverInterface
{

    protected $webpayOrderDataFactory;
    protected $_logger;
    protected $shippingMethod;
    protected $configProvider;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory)
    {
        $this->_logger = $logger;
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $order = $observer->getEvent()->getOrder();

        $this->_logger->debug(':: ORDEN CANCELADA');

        list($webpayOrderData, $commerceCode, $childCommerceCode, $amount, $metadata, $buyOrder, $childBuyOrder) = $this->getTransaction($order->getId());

        if (isset($commerceCode) && isset($childCommerceCode)) {
            $config = $this->configProvider->getPluginConfigOneclick();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
            
            $response = $transbankSdkWebpay->refundTransaction($buyOrder, $childCommerceCode, $childBuyOrder, $amount);

            $this->_logger->debug(json_encode($response));

            $webpayOrderData->setMetadata(json_encode($response). ' ' .$metadata);
            $webpayOrderData->setPaymentStatus(OneclickInscriptionData::PAYMENT_STATUS_REVERSED);
            $webpayOrderData->save();

            $order->addStatusHistoryComment(json_encode($response), true);
            $order->save();
        }
    }
    
    /**
     * @param $tokenWs
     *
     * @return array
     */
    private function getTransaction($orderId)
    {
        $webpayOrderDataModel = $this->webpayOrderDataFactory->create();
        $webpayOrderData = $webpayOrderDataModel->load($orderId, 'order_id');
        $commerceCode = $webpayOrderData->getCommerceCode();
        $childCommerceCode = $webpayOrderData->getChildCommerceCode();
        $amount = floatval($webpayOrderData->getAmount());
        $metadata = $webpayOrderData->getMetadata();
        $buyOrder = $webpayOrderData->getBuyOrder();
        $childBuyOrder = $webpayOrderData->getChildBuyOrder();

        return [$webpayOrderData, $commerceCode, $childCommerceCode, $amount, $metadata, $buyOrder, $childBuyOrder];
    }

    
}
