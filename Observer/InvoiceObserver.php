<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class InvoiceObserver implements ObserverInterface
{

    protected $orderSender;
    protected $invoiceSender;
    protected $_logger;


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

        $order = $observer->getEvent()->getOrder();

        $invoiceSettings = $this->configProvider->getInvoiceSettings();
        $invoiceOneclickSettings = $this->configProvider->getOneclickInvoiceSettings();

        if ($invoiceSettings == 'transbank' || $invoiceOneclickSettings == 'transbank') {

            $order->addStatusHistoryComment('Automatically Invoiced by Transbank', true);

            $this->_logger->debug('Creating Invoice email.');

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
