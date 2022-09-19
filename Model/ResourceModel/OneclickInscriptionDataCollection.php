<?php

namespace Transbank\Webpay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class OneclickInscriptionDataCollection extends AbstractCollection
{
    /**
     * Initialize resource.
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('Transbank\Oneclick', 'id');
    }
}
