<?php

namespace Transbank\Webpay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Inscriptions extends AbstractHelper
{
    protected $oneclickInscriptionDataFactory;
    protected $resourceConnection;
    protected $session;
    protected $scopeConfig;

    public function __construct(
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        \Magento\Customer\Model\Session $session,
        Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->session = $session;
        $this->resourceConnection = $resourceConnection;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }
    public function getInscriptions()
    {
        $customerId = $this->session->getCustomer()->getId();

        if (isset($customerId)) {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $connection->getTableName(OneclickInscriptionData::TABLE_NAME);

            $select = $connection->select()
                ->from(
                    ['t' => $tableName],
                    ['id', 'username', 'card_type', 'card_number']
                )
                ->where('t.user_id = ?', $customerId)
                ->where('t.status = ?', 'SUCCESS');

            $result = $connection->fetchAll($select);
        } else {
            $result = [];
        }

        return $result;
    }
}
