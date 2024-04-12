<?php
namespace Transbank\Webpay\Helper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData;


class Inscriptions extends AbstractHelper {
    protected $oneclickInscriptionDataFactory;
    protected $resourceConnection;
    protected $session;
    protected $scopeConfig;

    public function __construct(
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        \Magento\Customer\Model\Session $session,
        Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection)
    {
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
            //Create Connection
            $connection = $this->resourceConnection->getConnection();
            // get table name
            $table = $connection->getTableName(OneclickInscriptionData::TABLE_NAME);
            // Select query
            $selectquery = "SELECT id, username, card_type, card_number FROM ".$table." WHERE user_id = ".$customerId." AND status = 'SUCCESS'";
            $result = $connection->fetchAll($selectquery);
        } else {
            $result = [];
        }

        return $result;

    }
}
