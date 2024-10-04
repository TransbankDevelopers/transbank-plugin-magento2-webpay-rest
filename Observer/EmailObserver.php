<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;

class EmailObserver extends SuccessObserver implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer) {

        $emailSettings = $this->configProvider->getEmailSettings();
        $oneclickEmailSettings = $this->configProvider->getOneclickEmailSettings();
        $order = $observer->getEvent()->getOrder();

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
