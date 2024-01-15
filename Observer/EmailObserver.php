<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class EmailObserver implements ObserverInterface
{

    protected $orderSender;
    protected $invoiceSender;
    protected $_logger;
    protected $order;
    protected $invoiceService;
    protected $transaction;
    protected $configProvider;


    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider)
    {
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $emailSettings = $this->configProvider->getEmailSettings();
        $oneclickEmailSettings = $this->configProvider->getOneclickEmailSettings();

        $order = $observer->getEvent()->getOrder();

        if ($emailSettings == 'transbank' || $oneclickEmailSettings == 'transbank') {
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
