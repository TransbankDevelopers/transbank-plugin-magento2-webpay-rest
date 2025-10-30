<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Observer\Util\ObserverGuard;

class InvoiceObserver extends SuccessObserver implements ObserverInterface
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
        $invoiceSettings = $this->configProvider->getInvoiceSettings();
        $invoiceOneclickSettings = $this->configProvider->getOneclickInvoiceSettings();
        $order = $this->observerGuard->getOrderFromObserverOrSession($observer);

        if (!$order) {
            return;
        }

        if (!$this->observerGuard->isTransbankPayment($order)) {
            return;
        }

        if ($invoiceSettings == 'transbank' || $invoiceOneclickSettings == 'transbank') {
            $order->addStatusHistoryComment('Automatically Invoiced by Transbank', true);
            $this->logger->debug('Creating Invoice email.');
            $order->setCanSendNewEmailFlag(true);
            $order->save();

            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();

                $transactionSave = $this->transaction->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
            }
        }
    }

}
