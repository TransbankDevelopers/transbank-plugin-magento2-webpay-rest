<?php

namespace Transbank\Webpay\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Class WebpayOrderDataFactory
 * Factory for creating instances of WebpayOrderData
 */
class WebpayOrderDataFactory
{
    protected $objectManager;

    /**
     * Constructor
     *
     * @param ObjectManagerInterface $objectManager Object manager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create a new instance of WebpayOrderData
     *
     * @param array $data Data to initialize the model
     *
     * @return WebpayOrderData
     */
    public function create(array $data = [])
    {
        return $this->objectManager->create(WebpayOrderData::class, $data);
    }
}
