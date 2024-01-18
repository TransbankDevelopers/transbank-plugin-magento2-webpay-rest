<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OneclickAvailable implements ObserverInterface
{
    protected $configProvider;
    protected $_logger;
    protected $shippingMethod;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider)
    {
        $this->_logger = $logger;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $config = $this->configProvider->getPluginConfigOneclick();
        $oneclickMaxAmount = $config['TRANSACTION_MAX_AMOUNT'];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');

        $grandTotal = $cart->getQuote()->getGrandTotal();

        if ($customerSession->isLoggedIn() == false || ($oneclickMaxAmount > 0
            && $grandTotal >= $oneclickMaxAmount)) {
            $this->_logger->debug("User is not logged in");

            if($observer->getEvent()->getMethodInstance()->getCode() == "transbank_oneclick"){
                $checkResult = $observer->getEvent()->getResult();
                $checkResult->setData('is_available', false);
           }
        }
    }

    
}
