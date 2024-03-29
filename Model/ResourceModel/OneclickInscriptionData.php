<?php

namespace Transbank\Webpay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OneclickInscriptionData extends AbstractDb
{
    const TABLE_NAME = 'oneclick_inscription_data';

    /**
     * Initialize resource.
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(static::TABLE_NAME, 'id');
    }
}

