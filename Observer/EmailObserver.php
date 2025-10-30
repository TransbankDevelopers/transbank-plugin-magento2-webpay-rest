<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Observer\Util\ObserverGuard;

class EmailObserver extends SuccessObserver implements ObserverInterface
{
    private ObserverGuard $observerGuard;
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        ObserverGuard $observerGuard
    ) {
        parent::__construct(
            $logger,
            $order,
            $orderSender,
            $invoiceSender,
            $invoiceService,
            $transaction,
            $configProvider
        );
        $this->observerGuard = $observerGuard;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $emailSettings = $this->configProvider->getEmailSettings();
        $oneclickEmailSettings = $this->configProvider->getOneclickEmailSettings();
        $order = $this->observerGuard->getOrderFromObserverOrSession($observer);

        if (!$order) {
            return;
        }

        if (!$this->observerGuard->isTransbankPayment($order)) {
            return;
        }

        if ($emailSettings == 'transbank' || $oneclickEmailSettings == 'transbank') {
            $order->setCanSendNewEmailFlag(true);
            $order->save();

            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }

    }

}
