<?php

namespace Transbank\Webpay\Observer\Util;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Transbank\Webpay\Model\Oneclick;
use Transbank\Webpay\Model\Webpay;
use Magento\Sales\Model\Order;

/**
 * Utility class to help observers to check conditions and retrieve common data.
 */
class ObserverGuard
{
    public const PAYMENT_METHOD_NAME = 'transbank_webpay';
    private RequestInterface $request;
    private OrderRepositoryInterface $orders;
    private CheckoutSession $checkoutSession;

    /**
     * Constructor
     */
    public function __construct(
        RequestInterface $request,
        OrderRepositoryInterface $orders,
        CheckoutSession $checkoutSession
    ) {
        $this->request = $request;
        $this->orders = $orders;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Check if the given order was paid using Transbank payment methods.
     *
     * @param OrderInterface|null $order
     *
     * @return bool
     */
    public function isTransbankPayment(?OrderInterface $order): bool
    {
        if (!$order || !$order->getPayment()) {
            return false;
        }

        $paymentMethod = $order->getPayment()->getMethod();
        return $paymentMethod == Webpay::CODE || $paymentMethod == Oneclick::CODE;
    }

    /**
     * Retrieve the order from the observer event or from the checkout session.
     *
     * @param Observer $observer
     *
     * @return Order|null
     */
    public function getOrderFromObserverOrSession(Observer $observer): ?Order
    {
        $order = null;

        if (method_exists($observer, 'getEvent')) {
            $event = $observer->getEvent();
            $order = $event->getOrder();

            if (!$order) {
                $creditmemo = $event->getCreditmemo();
                if ($creditmemo) {
                    $order = $creditmemo->getOrder();
                }
            }
        }

        if (!$order) {
            $lastOrderId = (int) $this->checkoutSession->getLastOrderId();
            if ($lastOrderId > 0) {
                $order = $this->orders->get($lastOrderId);
            }
        }

        return $order;
    }

    /**
     * Check if the current configuration change event is related to this module.
     *
     * @return bool
     */
    public function isConfigChangeForThisModule(): bool
    {
        $section = (string) $this->request->getParam('section');
        if ($section !== 'payment') {
            return false;
        }

        $groups = (array) $this->request->getParam('groups', []);
        foreach (array_keys($groups) as $group) {
            if (strpos($group, 'transbank') === 0) {
                return true;
            }
        }

        return false;
    }
}
