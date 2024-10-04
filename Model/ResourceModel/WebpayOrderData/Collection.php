<?php

namespace Transbank\Webpay\Model\ResourceModel\WebpayOrderData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 * Collection for WebpayOrderData model
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'Transbank\Webpay\Model\WebpayOrderData',
            'Transbank\Webpay\Model\ResourceModel\WebpayOrderData'
        );
    }
}
