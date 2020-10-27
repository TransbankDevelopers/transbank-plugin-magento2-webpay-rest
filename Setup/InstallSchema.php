<?php
namespace Transbank\Webpay\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Upgrade the Catalog module DB scheme
 */
class InstallSchema implements InstallSchemaInterface
{
    use CreatesWebpayOrdersTable;
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $this->createWebpayOrdersTable($setup);
        $setup->endSetup();
    }

    
}
