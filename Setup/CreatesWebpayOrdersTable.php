<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Transbank\Webpay\Model\ResourceModel\WebpayOrderData;

trait CreatesWebpayOrdersTable
{
    protected function createWebpayOrdersTable(SchemaSetupInterface $setup)
    {
        $mainTable = $setup->getTable(WebpayOrderData::TABLE_NAME);
        $table = $setup->getConnection()
            ->newTable($mainTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true
            ], 'ID')
            ->addColumn('token', Table::TYPE_TEXT, 200, [
                'nullable' => false
            ], 'Token')
            ->addColumn('order_id', Table::TYPE_TEXT, 20, [
                'nullable' => false
            ], 'Order Id')
            ->addColumn('quote_id', Table::TYPE_TEXT, 20, [
                'nullable' => false
            ], 'Quote ID')
            ->addColumn('payment_status', Table::TYPE_TEXT, 30, [
                'nullable' => false
            ], 'Payment Status')
            ->addColumn('metadata', Table::TYPE_TEXT, null, [
                'nullable' => false
            ], 'Metadata')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT
            ], 'created_at')
            ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT_UPDATE
            ], 'updated_at')
            ->addIndex($setup->getTable(WebpayOrderData::TABLE_NAME), 'token');
        $setup->getConnection()->createTable($table);
    }
}
