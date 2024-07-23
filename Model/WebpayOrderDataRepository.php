<?php

namespace Transbank\Webpay\Model;

use Transbank\Webpay\Model\WebpayOrderDataFactory;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData\CollectionFactory;

/**
 * Class WebpayOrderDataRepository
 * Repository for WebpayOrderData model
 */
class WebpayOrderDataRepository
{
    protected $webpayOrderDataFactory;
    protected $collectionFactory;

    /**
     * Constructor
     *
     * @param WebpayOrderDataFactory $webpayOrderDataFactory Factory for creating WebpayOrderData instances
     * @param CollectionFactory      $collectionFactory      Factory for creating Collection instances
     */
    public function __construct(
        WebpayOrderDataFactory $webpayOrderDataFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get WebpayOrderData by order ID and quote ID
     *
     * @param string $orderId The order ID
     * @param string $quoteId The quote ID
     *
     * @return WebpayOrderData
     */
    public function getByOrderIdAndQuoteId(int $orderId, int $quoteId): WebpayOrderData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId)
            ->addFieldToFilter('quote_id', $quoteId);

        return $collection->getFirstItem();
    }
}
