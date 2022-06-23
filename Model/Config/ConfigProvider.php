<?php

namespace Transbank\Webpay\Model\Config;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const SECURITY_CONFIGS_ROUTE = 'payment/transbank_webpay/security/';
    const ORDER_CONFIGS_ROUTE = 'payment/transbank_webpay/general_parameters/';

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface)
    {
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    public function getConfig()
    {
        return [
            'pluginConfigWebpay' => [
                'createTransactionUrl' => 'transaction/createwebpay',
            ],
        ];
    }

    public function getPluginConfig()
    {
        $config = [
            'ENVIRONMENT'               => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'environment'),
            'COMMERCE_CODE'             => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'commerce_code'),
            'API_KEY'                   => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'api_key'),
            'URL_RETURN'                => 'checkout/transaction/commitwebpay',
            'ECOMMERCE'                 => 'magento',
            'new_order_status'          => $this->getOrderPendingStatus(),
            'payment_successful_status' => $this->getOrderSuccessStatus(),
            'payment_error_status'      => $this->getOrderErrorStatus(),
            'new_email_order'           => $this->getEmailSettings(),
            'invoice_settings'          => $this->getInvoiceSettings(),
        ];

        return $config;
    }

    public function getPluginConfigOneclick()
    {
        $config = [
            'ENVIRONMENT'               => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'environment'),
            'COMMERCE_CODE'             => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'commerce_code'),
            'API_KEY'                   => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE.'api_key'),
            'URL_RETURN'                => 'checkout/transaction/commitoneclick',
            'ECOMMERCE'                 => 'magento',
            'new_order_status'          => $this->getOrderPendingStatus(),
            'payment_successful_status' => $this->getOrderSuccessStatus(),
            'payment_error_status'      => $this->getOrderErrorStatus(),
            'new_email_order'           => $this->getEmailSettings(),
            'invoice_settings'          => $this->getInvoiceSettings(),
        ];

        return $config;
    }

    public function getOrderPendingStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE.'new_order_status');
    }

    public function getOrderSuccessStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE.'payment_successful_status');
    }

    public function getOrderErrorStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE.'payment_error_status');
    }

    public function getEmailSettings()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE.'new_email_order');
    }

    public function getInvoiceSettings()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE.'invoice_settings');
    }
}
