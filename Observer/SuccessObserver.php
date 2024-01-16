<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

abstract class SuccessObserver implements ObserverInterface
{
    protected $orderSender;
    protected $invoiceSender;
    protected $logger;
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
        $this->logger = $logger;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->configProvider = $configProvider;
    }

}
