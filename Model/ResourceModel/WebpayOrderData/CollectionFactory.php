<?php

namespace Transbank\Webpay\Model\ResourceModel\WebpayOrderData;

use Magento\Framework\ObjectManagerInterface;

class CollectionFactory
{
    protected $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function create(array $data = [])
    {
        return $this->objectManager->create(Collection::class, $data);
    }
}
