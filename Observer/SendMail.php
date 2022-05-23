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
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider)
    {
        $this->_logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->configProvider = $configProvider;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $emailSettings = $this->configProvider->getEmailSettings();
        $this->_logger->debug($emailSettings);

        $order = $observer->getEvent()->getOrder();

        if ($emailSettings == 'transbank') {
            $this->_current_order = $order;
    
            $order->setCanSendNewEmailFlag(true);
            $order->save();

            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }

        $invoiceSettings = $this->configProvider->getInvoiceSettings();
        if ($invoiceSettings == 'transbank') {

            $order->addStatusHistoryComment('Automatically Invoiced by Transbank', true);

            $this->_logger->debug('Creating Invoice');

            $order->setCanSendNewEmailFlag(true);
            $order->save();

            if ($order->canInvoice()) {

                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();
                
                $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();
            }
        }
    }

    
}
