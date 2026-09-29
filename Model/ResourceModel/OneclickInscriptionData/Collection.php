<?php

namespace Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Initialize resource.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Transbank\Webpay\Model\OneclickInscriptionData::class,
            \Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData::class
        );
    }
}
