<?php
namespace Transbank\Webpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData;

/**
 * Upgrade the Catalog module DB scheme
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    use CreatesWebpayOrdersTable;
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), '3.3.0', '<')) {
            $setup->endSetup();
            return;
        }

        $mainTable = $setup->getTable(WebpayOrderData::TABLE_NAME);
        if ($setup->getConnection()->isTableExists($mainTable) === true) {
            $setup->endSetup();
            return;
        }

        $this->createWebpayOrdersTable($setup);
        $setup->endSetup();
    }
}
