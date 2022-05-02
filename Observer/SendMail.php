<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class SendMail implements ObserverInterface
{

    protected $orderSender;
    protected $_logger;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider)
    {
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $emailSettings = $this->configProvider->getEmailSettings();
        $this->_logger->debug($emailSettings);

        if ($emailSettings == 'transbank') {
            $order = $observer->getEvent()->getOrder();
            $this->_current_order = $order;
    
            $order->setCanSendNewEmailFlag(true);
            $order->save();

            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }
    }
    
}
