<?php

namespace Transbank\Webpay\Setup;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class UpgradeData implements UpgradeDataInterface
{
    private $configInterface;
    private $scopeConfigInterface;

    public function __construct(
        ConfigInterface $configInterface,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->configInterface = $configInterface;
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '2.2.1', '<')) {
            $this->updateConfigData();
        }

        $setup->endSetup();
    }

    private function updateConfigData()
    {
        $newConfigValues = [
            'payment/transbank_webpay/general_parameters/payment_successful_status' =>
            'payment/transbank_webpay/payment_successful_status',
            'payment/transbank_webpay/general_parameters/payment_error_status' =>
            'payment/transbank_webpay/payment_error_status',
            'payment/transbank_webpay/general_parameters/new_order_status' =>
            'payment/transbank_webpay/new_order_status',
            'payment/transbank_webpay/general_parameters/invoice_settings' =>
            'payment/transbank_webpay/invoice_settings',
            'payment/transbank_webpay/general_parameters/sort_order' => 'payment/transbank_webpay/sort_order',
            'payment/transbank_webpay/general_parameters/new_email_order' =>
            'payment/transbank_webpay/new_email_order',
            'payment/transbank_webpay/security/environment' => 'payment/transbank_webpay/environment',
            'payment/transbank_webpay/security/commerce_code' => 'payment/transbank_webpay/commerce_code',
            'payment/transbank_webpay/security/api_key' => 'payment/transbank_webpay/api_key',
            'payment/transbank_oneclick/general_parameters/payment_successful_status' =>
            'payment/transbank_oneclick/payment_successful_status',
            'payment/transbank_oneclick/general_parameters/payment_error_status' =>
            'payment/transbank_oneclick/payment_error_status',
            'payment/transbank_oneclick/general_parameters/invoice_settings' =>
            'payment/transbank_oneclick/invoice_settings',
            'payment/transbank_oneclick/general_parameters/new_order_status' =>
            'payment/transbank_oneclick/new_order_status',
            'payment/transbank_oneclick/general_parameters/sort_order' => 'payment/transbank_oneclick/sort_order',
            'payment/transbank_oneclick/general_parameters/new_email_order' =>
            'payment/transbank_oneclick/new_email_order',
            'payment/transbank_oneclick/security/environment' => 'payment/transbank_oneclick/environment',
            'payment/transbank_oneclick/security/commerce_code' => 'payment/transbank_oneclick/commerce_code',
            'payment/transbank_oneclick/security/child_commerce_code' =>
            'payment/transbank_oneclick/child_commerce_code',
            'payment/transbank_oneclick/security/transaction_max_amount' =>
            'payment/transbank_oneclick/transaction_max_amount',
            'payment/transbank_oneclick/security/api_key' => 'payment/transbank_oneclick/api_key',
        ];

        foreach ($newConfigValues as $key => $value) {
            $currentConfigValue = $this->scopeConfigInterface->getValue($key);
            $this->configInterface->deleteConfig($key);
            $this->configInterface->saveConfig($value, $currentConfigValue);
        }
    }
}
