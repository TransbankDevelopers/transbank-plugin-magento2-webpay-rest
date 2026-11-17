<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

abstract class SuccessObserver implements ObserverInterface
{
    protected $orderSender;
    protected $logger;
    protected $invoiceService;
    protected $transaction;
    protected $configProvider;
    protected $orderRepository;


    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        ?OrderRepositoryInterface $orderRepository = null)
    {
        $this->logger = $logger;
        $this->orderSender = $orderSender;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->configProvider = $configProvider;
        $this->orderRepository = $orderRepository ?? ObjectManager::getInstance()->get(OrderRepositoryInterface::class);
    }

}
