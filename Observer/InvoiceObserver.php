<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class InvoiceObserver extends SuccessObserver implements ObserverInterface
{

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $order = $observer->getEvent()->getOrder();

        $invoiceSettings = $this->configProvider->getInvoiceSettings();
        $invoiceOneclickSettings = $this->configProvider->getOneclickInvoiceSettings();

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
