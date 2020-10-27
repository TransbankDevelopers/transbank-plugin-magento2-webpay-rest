<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData;

class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $installer->getConnection()->dropTable($installer->getTable(WebpayOrderData::TABLE_NAME));

        $installer->endSetup();
    }
}
