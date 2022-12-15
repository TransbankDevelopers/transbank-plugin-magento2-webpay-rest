<?php

namespace Transbank\Webpay\Model;

use Magento\Framework\Model\AbstractModel;

class OneclickInscriptionData extends AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'oneclick_inscription_data';
    const PAYMENT_STATUS_WATING = 'WAITING';
    const PAYMENT_STATUS_DELETED = 'DELETED';
    const PAYMENT_STATUS_SUCCESS = 'SUCCESS';
    const PAYMENT_STATUS_FAILED = 'FAILED';
    const PAYMENT_STATUS_CANCELED_BY_USER = 'FAILED';
    const PAYMENT_STATUS_ERROR = 'ERROR';
    const PAYMENT_STATUS_REVERSED = 'REVERSED';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData::class);
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG.'_'.$this->getId()];
    }
}
