<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData;

/**
 * Upgrade the Catalog module DB scheme.
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    use CreatesWebpayOrdersTable;
    use CreatesOneclickInscriptionTable;

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

        $webPayTable = $setup->getTable(WebpayOrderData::TABLE_NAME);
        if ($setup->getConnection()->isTableExists($webPayTable) === false) {
            $this->createWebpayOrdersTable($setup);
        }

        $oneClickTable = $setup->getTable(OneclickInscriptionData::TABLE_NAME);
        if ($setup->getConnection()->isTableExists($oneClickTable) === false) {
            $this->createOneclickInscriptionTable($setup);
        }

        $setup->endSetup();
    }
}
