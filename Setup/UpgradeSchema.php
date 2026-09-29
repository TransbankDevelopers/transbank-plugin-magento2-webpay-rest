<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\DB\Ddl\Table;
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

        $webPayTable = $setup->getTable(WebpayOrderData::TABLE_NAME);
        if ($setup->getConnection()->isTableExists($webPayTable) === false) {
            $this->createWebpayOrdersTable($setup);
        }

        $oneClickTable = $setup->getTable(OneclickInscriptionData::TABLE_NAME);
        if ($setup->getConnection()->isTableExists($oneClickTable) === false) {
            $this->createOneclickInscriptionTable($setup);
        } elseif (version_compare((string) $context->getVersion(), '2.3.1', '<')) {
            $this->allowPrivateInscriptionFields($setup, $oneClickTable);
        }

        $setup->endSetup();
    }

    private function allowPrivateInscriptionFields(SchemaSetupInterface $setup, string $table): void
    {
        $connection = $setup->getConnection();
        $columns = $connection->describeTable($table);

        $nullableColumns = [
            'tbk_user' => [Table::TYPE_TEXT, 100],
            'token_id' => [Table::TYPE_TEXT, 60],
            'order_id' => [Table::TYPE_TEXT, 60],
            'pay_after_inscription' => [Table::TYPE_BIGINT, 30],
            'finished' => [Table::TYPE_TEXT, 60],
            'response_code' => [Table::TYPE_TEXT, 20],
            'authorization_code' => [Table::TYPE_TEXT, 20],
            'card_type' => [Table::TYPE_TEXT, 20],
            'card_number' => [Table::TYPE_TEXT, 20],
            'from' => [Table::TYPE_TEXT, 50],
            'transbank_response' => [Table::TYPE_TEXT, null],
            'quote_id' => [Table::TYPE_TEXT, 20],
        ];

        foreach ($nullableColumns as $column => [$type, $length]) {
            if (!isset($columns[$column])) {
                continue;
            }

            $definition = [
                'type' => $type,
                'length' => $length,
                'nullable' => true,
            ];
            $connection->changeColumn($table, $column, $column, $definition);
        }
    }
}
