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
                'primary'  => true,
            ], 'ID')
            ->addColumn('order_id', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Order Id')
            ->addColumn('buy_order', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Buy order')
            ->addColumn('child_buy_order', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Child buy order')
            ->addColumn('commerce_code', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Commerce code')
            ->addColumn('child_commerce_code', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Child commerce code')
            ->addColumn('amount', Table::TYPE_BIGINT, 20, [
                'nullable' => false,
            ], 'Amount')
            ->addColumn('token', Table::TYPE_TEXT, 100, [
                'nullable' => false,
            ], 'Token')
            ->addColumn('transbank_status', Table::TYPE_TEXT, null, [
                'nullable' => false,
            ], 'Transbank status')
            ->addColumn('session_id', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Session ID')
            ->addColumn('quote_id', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Quote ID')
            ->addColumn('payment_status', Table::TYPE_TEXT, 30, [
                'nullable' => false,
            ], 'Payment Status')
            ->addColumn('metadata', Table::TYPE_TEXT, null, [
                'nullable' => false,
            ], 'Metadata')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT,
            ], 'created_at')
            ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default'  => Table::TIMESTAMP_INIT_UPDATE,
            ], 'updated_at')
            ->addColumn('product', Table::TYPE_TEXT, 50, [
                'nullable' => false,
            ], 'Product')
            ->addColumn('environment', Table::TYPE_TEXT, 50, [
                'nullable' => false,
            ], 'Environment')
            ->addIndex($setup->getTable(WebpayOrderData::TABLE_NAME), 'token');
        $setup->getConnection()->createTable($table);
    }
}
