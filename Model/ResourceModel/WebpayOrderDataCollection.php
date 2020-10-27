<?php
namespace Transbank\Webpay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class WebpayOrderDataCollection extends AbstractCollection
{
    /**
     * Initialize resource
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('Transbank\Webpay', 'id');
    }
}
