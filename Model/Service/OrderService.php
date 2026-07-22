<?php

namespace Transbank\Webpay\Model\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Transbank\Webpay\Exceptions\OrderNotFoundException;

/**
 * Class OrderService
 * Order administration shared by the plugin's controllers, backed by Magento's official Order repository.
 */
class OrderService
{
    protected $orderRepository;

    /**
     * Constructor
     *
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Get an Order by id.
     *
     * @param int $orderId The order id.
     *
     * @throws OrderNotFoundException When no order matches the given id.
     *
     * @return Order
     */
    public function getById(int $orderId): Order
    {
        try {
            return $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new OrderNotFoundException();
        }
    }

    /**
     * Persist an Order.
     *
     * @param Order $order The order to persist.
     *
     * @return Order
     */
    public function save(Order $order): Order
    {
        return $this->orderRepository->save($order);
    }

    /**
     * Set an order's status, add a history comment and save, in a single save.
     *
     * @param Order  $order   The order to update.
     * @param string $status  The status to set.
     * @param string $message The message to add to the order history.
     *
     * @return Order
     */
    public function setStatus(Order $order, string $status, string $message): Order
    {
        $order->setStatus($status);
        $order->addStatusToHistory($order->getStatus(), $message);

        return $this->orderRepository->save($order);
    }

    /**
     * Set an order's state and status to the given value, and add a history comment, with a single save.
     *
     * @param Order  $order   The order to update.
     * @param string $status  The state and status to set.
     * @param string $message The message to add to the order history.
     *
     * @return Order
     */
    public function setStateAndStatus(Order $order, string $status, string $message): Order
    {
        $order->setState($status);

        return $this->setStatus($order, $status, $message);
    }

    /**
     * Cancel an order, set its status and add a history comment, with a single save.
     *
     * @param Order  $order   The order to cancel.
     * @param string $status  The status to set after cancellation.
     * @param string $message The message to add to the order history.
     *
     * @return Order
     */
    public function cancel(Order $order, string $status, string $message): Order
    {
        $order->cancel();

        return $this->setStatus($order, $status, $message);
    }

    /**
     * Determine whether an order is in the given canceled status.
     *
     * @param Order  $order          The order to check.
     * @param string $canceledStatus The status considered canceled.
     *
     * @return bool
     */
    public function isCanceled(Order $order, string $canceledStatus): bool
    {
        return $order->getStatus() === $canceledStatus;
    }
}
