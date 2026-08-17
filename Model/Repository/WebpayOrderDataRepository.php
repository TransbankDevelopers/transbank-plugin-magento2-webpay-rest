<?php

namespace Transbank\Webpay\Model\Repository;

use Transbank\Webpay\Exceptions\WebpayOrderDataNotFoundException;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData\CollectionFactory;

/**
 * Class WebpayOrderDataRepository
 * Repository for WebpayOrderData model
 */
class WebpayOrderDataRepository
{
    protected $collectionFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $collectionFactory Factory for creating Collection instances
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get WebpayOrderData by order ID and quote ID
     *
     * @param string $orderId The order ID
     * @param string $quoteId The quote ID
     *
     * @throws WebpayOrderDataNotFoundException When no record matches the given order ID and quote ID
     *
     * @return WebpayOrderData
     */
    public function getByOrderIdAndQuoteId(int $orderId, int $quoteId): WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('quote_id', $quoteId);
        $webpayOrderData = $collection->getFirstItem();

        if (!$webpayOrderData->getId()) {
            throw new WebpayOrderDataNotFoundException();
        }

        return $webpayOrderData;
    }

    /**
     * Get WebpayOrderData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @throws WebpayOrderDataNotFoundException When no record matches the given token
     *
     * @return WebpayOrderData
     */
    public function getByToken(string $token): WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('token', $token);
        $webpayOrderData = $collection->getFirstItem();

        if (!$webpayOrderData->getId()) {
            throw new WebpayOrderDataNotFoundException();
        }

        return $webpayOrderData;
    }

    /**
     * Get WebpayOrderData by buy order
     *
     * @param string $buyOrder The buy order
     *
     * @throws WebpayOrderDataNotFoundException When no record matches the given buy order
     *
     * @return WebpayOrderData
     */
    public function getByBuyOrder(string $buyOrder): WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('buy_order', $buyOrder);
        $webpayOrderData = $collection->getFirstItem();

        if (!$webpayOrderData->getId()) {
            throw new WebpayOrderDataNotFoundException();
        }

        return $webpayOrderData;
    }

    /**
     * Get WebpayOrderData by order ID
     *
     * @param string $orderId The order ID
     *
     * @throws WebpayOrderDataNotFoundException When no record matches the given order ID
     *
     * @return WebpayOrderData
     */
    public function getByOrderId(string $orderId): WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);
        $webpayOrderData = $collection->getFirstItem();

        if (!$webpayOrderData->getId()) {
            throw new WebpayOrderDataNotFoundException();
        }

        return $webpayOrderData;
    }

    /**
     * Create and persist a new WebpayOrderData entity
     *
     * @param array $data The data to initialize the entity with
     *
     * @return WebpayOrderData
     */
    public function create(array $data): WebpayOrderData
    {
        $webpayOrderData = $this->collectionFactory->create()->getNewEmptyItem();
        $webpayOrderData->setData($data);
        $webpayOrderData->save();

        return $webpayOrderData;
    }

    /**
     * Persist a WebpayOrderData entity
     *
     * @param WebpayOrderData $webpayOrderData The entity to persist
     *
     * @return WebpayOrderData
     */
    public function save(WebpayOrderData $webpayOrderData): WebpayOrderData
    {
        $webpayOrderData->save();

        return $webpayOrderData;
    }
}
