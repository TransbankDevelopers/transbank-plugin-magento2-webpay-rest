<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class SubmitObserver implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    protected $_logger;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
        )
    {
        $this->_logger = $logger;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            
            $emailSettings = $this->configProvider->getEmailSettings();
            $this->_logger->debug($emailSettings);

            if ($emailSettings == 'transbank') {
                $order = $observer->getEvent()->getOrder();
                $this->_current_order = $order;

                $order->setCanSendNewEmailFlag(false);
                $order->save();
            }

        } catch (\ErrorException $e) {
            $this->_logger->critical($e);
        }
    }
    
}