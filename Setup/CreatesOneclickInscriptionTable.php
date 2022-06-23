<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\SchemaSetupInterface;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData;

trait CreatesOneclickInscriptionTable
{
    protected function createOneclickInscriptionTable(SchemaSetupInterface $setup)
    {
        $mainTable = $setup->getTable(OneclickInscriptionData::TABLE_NAME);
        $table = $setup->getConnection()
            ->newTable($mainTable)
            ->addColumn('id', Table::TYPE_INTEGER, null, [
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ], 'ID')
            ->addColumn('token', Table::TYPE_TEXT, 100, [
                'nullable' => false,
            ], 'Token')
            ->addColumn('tbk_user', Table::TYPE_TEXT, 100, [
                'nullable' => false,
            ], 'TBK User')
            ->addColumn('username', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Username')
            ->addColumn('email', Table::TYPE_TEXT, 100, [
                'nullable' => false,
            ], 'Email')
            ->addColumn('user_id', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'User ID')
            ->addColumn('token_id', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Token ID')
            ->addColumn('order_id', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Order ID')
            ->addColumn('pay_after_inscription', Table::TYPE_BIGINT, 30, [
                'nullable' => false,
            ], 'Pay After Inscription')
            ->addColumn('finished', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Finished')
            ->addColumn('response_code', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Response Code')
            ->addColumn('authorization_code', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Authorization Code')
            ->addColumn('card_type', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Card Type')
            ->addColumn('card_number', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Card_number')
            ->addColumn('from', Table::TYPE_TEXT, 50, [
                'nullable' => false,
            ], 'From')
            ->addColumn('status', Table::TYPE_TEXT, 50, [
                'nullable' => false,
            ], 'Status')
            ->addColumn('environment', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Environment')
            ->addColumn('commerce_code', Table::TYPE_TEXT, 60, [
                'nullable' => false,
            ], 'Commerce Code')
            ->addColumn('transbank_response', Table::TYPE_TEXT, null, [
                'nullable' => false,
            ], 'Transbank Response')
            ->addColumn('quote_id', Table::TYPE_TEXT, 20, [
                'nullable' => false,
            ], 'Quote ID')
            ->addColumn('metadata', Table::TYPE_TEXT, null, [
                'nullable' => false,
            ], 'Metadata')
            ->addColumn('created_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT,
            ], 'created_at')
            ->addColumn('updated_at', Table::TYPE_TIMESTAMP, null, [
                'nullable' => false,
                'default' => Table::TIMESTAMP_INIT_UPDATE,
            ], 'updated_at')
            ->addIndex($setup->getTable(OneclickInscriptionData::TABLE_NAME), 'token');
        $setup->getConnection()->createTable($table);
    }
}
