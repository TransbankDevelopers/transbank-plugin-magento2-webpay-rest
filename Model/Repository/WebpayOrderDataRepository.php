<?php

namespace Transbank\Webpay\Model\Repository;

use Transbank\Webpay\Exceptions\WebpayOrderDataNotFoundException;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData as WebpayOrderDataResource;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData\CollectionFactory;

/**
 * Class WebpayOrderDataRepository
 * Repository for WebpayOrderData model
 */
class WebpayOrderDataRepository
{
    /**
     * Columns writable through create()/update(). Anything outside this list
     * (e.g. id, created_at, updated_at) is managed by Magento.
     */
    private const WRITABLE_FIELDS = [
        'token',
        'payment_status',
        'order_id',
        'buy_order',
        'child_buy_order',
        'commerce_code',
        'child_commerce_code',
        'quote_id',
        'amount',
        'metadata',
        'environment',
        'product',
    ];

    protected $collectionFactory;
    private WebpayOrderDataResource $resource;

    /**
     * Constructor
     *
     * @param CollectionFactory $collectionFactory Factory for creating Collection instances
     * @param WebpayOrderDataResource $resource Resource model responsible for persistence
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        WebpayOrderDataResource $resource
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resource;
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
     * @param array $data The data to initialize the entity with. Keys outside
     *              WRITABLE_FIELDS are silently discarded.
     *
     * @return WebpayOrderData
     */
    public function create(array $data): WebpayOrderData
    {
        $webpayOrderData = $this->collectionFactory->create()->getNewEmptyItem();
        $webpayOrderData->setData($this->filterWritableFields($data));
        $this->resource->save($webpayOrderData);

        return $webpayOrderData;
    }

    /**
     * Update a WebpayOrderData entity with the given fields and persist it.
     *
     * @param WebpayOrderData $webpayOrderData The entity to update
     * @param array $data The fields to update. Keys outside WRITABLE_FIELDS
     *              are silently discarded.
     *
     * @return WebpayOrderData
     */
    public function update(WebpayOrderData $webpayOrderData, array $data): WebpayOrderData
    {
        $webpayOrderData->addData($this->filterWritableFields($data));
        $this->resource->save($webpayOrderData);

        return $webpayOrderData;
    }

    /**
     * Restrict a data array to the columns this repository allows writing to.
     *
     * @param array $data
     *
     * @return array
     */
    private function filterWritableFields(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE_FIELDS));
    }
}
