<?php

namespace Transbank\Webpay\Model\Repository;

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
     * @return WebpayOrderData|null Null when no record matches the given order ID and quote ID
     */
    public function getByOrderIdAndQuoteId(int $orderId, int $quoteId): ?WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('quote_id', $quoteId);

        return $this->firstOrNull($collection);
    }

    /**
     * Get WebpayOrderData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @return WebpayOrderData|null Null when no record matches the given token
     */
    public function getByToken(string $token): ?WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('token', $token);

        return $this->firstOrNull($collection);
    }

    /**
     * Get WebpayOrderData by buy order
     *
     * @param string $buyOrder The buy order
     *
     * @return WebpayOrderData|null Null when no record matches the given buy order
     */
    public function getByBuyOrder(string $buyOrder): ?WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('buy_order', $buyOrder);

        return $this->firstOrNull($collection);
    }

    /**
     * Get WebpayOrderData by order ID
     *
     * @param string $orderId The order ID
     *
     * @return WebpayOrderData|null Null when no record matches the given order ID
     */
    public function getByOrderId(string $orderId): ?WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);

        return $this->firstOrNull($collection);
    }

    /**
     * Return the first item of a collection, or null when it has no match.
     *
     * @param \Transbank\Webpay\Model\ResourceModel\WebpayOrderData\Collection $collection
     *
     * @return WebpayOrderData|null
     */
    private function firstOrNull($collection): ?WebpayOrderData
    {
        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
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
