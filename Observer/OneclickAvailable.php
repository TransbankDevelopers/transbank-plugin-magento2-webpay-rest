<?php

namespace Transbank\Webpay\Observer;

use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Helper\ObjectManagerHelper;

class OneclickAvailable implements ObserverInterface
{
    protected $configProvider;
    protected $logger;
    protected $shippingMethod;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider)
    {
        $this->logger = $logger;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $config = $this->configProvider->getPluginConfigOneclick();
        $oneclickMaxAmount = $config['TRANSACTION_MAX_AMOUNT'];

        $customerSession = ObjectManagerHelper::get(Session::class);
        $cart = ObjectManagerHelper::get(Cart::class);

        $grandTotal = $cart->getQuote()->getGrandTotal();

        if ($customerSession->isLoggedIn() == false || ($oneclickMaxAmount > 0
            && $grandTotal >= $oneclickMaxAmount)) {
            $this->logger->debug("Oneclick is not available");

            if($observer->getEvent()->getMethodInstance()->getCode() == "transbank_oneclick"){
                $checkResult = $observer->getEvent()->getResult();
                $checkResult->setData('is_available', false);
           }
        }
    }


}
