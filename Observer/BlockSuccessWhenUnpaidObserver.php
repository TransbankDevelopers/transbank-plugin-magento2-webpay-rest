<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\ActionFlag;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;

class BlockSuccessWhenUnpaidObserver implements ObserverInterface
{
    private Session $checkoutSession;
    private OrderRepositoryInterface $orderRepository;
    private UrlInterface $url;
    private RedirectInterface $redirect;
    private ActionFlag $actionFlag;

    public function __construct(
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        UrlInterface $url,
        RedirectInterface $redirect,
        ActionFlag $actionFlag
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->url = $url;
        $this->redirect = $redirect;
        $this->actionFlag = $actionFlag;
    }

    public function execute(Observer $observer)
    {
        $lastOrderId = (int) $this->checkoutSession->getLastOrderId();
        if ($lastOrderId <= 0) {
            return;
        }

        $order = $this->orderRepository->get($lastOrderId);
        $isMyMethod = $order->getPayment() && $order->getPayment()->getMethod() === 'transbank_webpay';
        if (!$isMyMethod) {
            return;
        }

        $isPaid = in_array(
            $order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE],
            true
        ) || (bool) $order->getBaseTotalInvoiced();

        if ($isPaid) {
            return;
        }

        $controller = $observer->getEvent()->getControllerAction();
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->redirect->redirect($controller->getResponse(), $this->url->getUrl('checkout/cart'));
    }
}
