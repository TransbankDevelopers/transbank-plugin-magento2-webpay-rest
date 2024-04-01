<?php

namespace Transbank\Webpay\Helper;

use Magento\Framework\App\ObjectManager;

/**
 * Helper class for retrieving objects using the ObjectManager.
 */
class ObjectManagerHelper
{
    /**
     * Retrieve a cached object instance from the ObjectManager.
     *
     * @param string $type The type of object to retrieve.
     * @return object The object instance.
     */
    public static function get(string $type)
    {
        $objectManager = ObjectManager::getInstance();
        return $objectManager->get($type);
    }
}
