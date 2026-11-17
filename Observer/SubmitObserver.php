<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Transbank\Webpay\Observer\Util\ObserverGuard;

class SubmitObserver implements ObserverInterface
{
    protected $configProvider;
    protected $_current_order;
    /**
     * @param Observer $observer
     * @return void
     */
    protected $_logger;

    private ObserverGuard $observerGuard;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        ObserverGuard $observerGuard,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->_logger = $logger;
        $this->configProvider = $configProvider;
        $this->observerGuard = $observerGuard;
        $this->orderRepository = $orderRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {

            $emailSettings = $this->configProvider->getEmailSettings();
            $oneclickEmailSettings = $this->configProvider->getOneclickEmailSettings();
            $this->_logger->debug($emailSettings);
            $order = $this->observerGuard->getOrderFromObserverOrSession($observer);

            if (!$order) {
                return;
            }

            if (!$this->observerGuard->isTransbankPayment($order)) {
                return;
            }

            if ($emailSettings == 'transbank' || $oneclickEmailSettings == 'transbank') {
                $this->_current_order = $order;

                $order->setCanSendNewEmailFlag(false);
                $this->orderRepository->save($order);
            }

        } catch (\ErrorException $e) {
            $this->_logger->critical($e->getMessage());
        }
    }

}
