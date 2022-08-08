<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class OneclickAvailable implements ObserverInterface
{

    protected $_logger;
    protected $shippingMethod;

    public function __construct (
        \Psr\Log\LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');
        if ($customerSession->isLoggedIn() == false) {
            $this->_logger->debug("User is not logged in");

            if($observer->getEvent()->getMethodInstance()->getCode() == "transbank_oneclick"){
                $checkResult = $observer->getEvent()->getResult();
                $checkResult->setData('is_available', false);
           }
        }
    }

    
}
