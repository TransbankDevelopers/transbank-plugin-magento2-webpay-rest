<?php

namespace Transbank\Webpay\Model\Config;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const SECURITY_CONFIGS_ROUTE = 'payment/transbank_webpay/';
    const ORDER_CONFIGS_ROUTE = 'payment/transbank_webpay/';

    const SECURITY_CONFIGS_ROUTE_ONECLICK = 'payment/transbank_oneclick/';
    const ORDER_CONFIGS_ROUTE_ONECLICK = 'payment/transbank_oneclick/';

    const CC_VAULT_CODE = 'transbank_oneclick_cc_vault';

    private $scopeConfigInterface;

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
            'pluginConfigOneclick' => [
                'createTransactionUrl' => 'transaction/createoneclick',
                'authorizeTransactionUrl' => 'transaction/authorizeoneclick',
            ]
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
            'ENVIRONMENT'               => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE_ONECLICK.'environment'),
            'COMMERCE_CODE'             => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE_ONECLICK.'commerce_code'),
            'CHILD_COMMERCE_CODE'       => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE_ONECLICK.'child_commerce_code'),
            'TRANSACTION_MAX_AMOUNT'    => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE_ONECLICK.'transaction_max_amount'),
            'API_KEY'                   => $this->scopeConfigInterface->getValue(self::SECURITY_CONFIGS_ROUTE_ONECLICK.'api_key'),
            'URL_RETURN'                => 'checkout/transaction/commitoneclick',
            'ECOMMERCE'                 => 'magento',
            'title'                     => $this->getOneclickTitle(),
            'new_order_status'          => $this->getOneclickOrderPendingStatus(),
            'payment_successful_status' => $this->getOneclickOrderSuccessStatus(),
            'payment_error_status'      => $this->getOneclickOrderErrorStatus(),
            'new_email_order'           => $this->getOneclickEmailSettings(),
            'invoice_settings'          => $this->getOneclickInvoiceSettings(),
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

    // Oneclick

    public function getOneclickTitle()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'title');
    }

    public function getOneclickOrderPendingStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'new_order_status');
    }

    public function getOneclickOrderSuccessStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'payment_successful_status');
    }

    public function getOneclickOrderErrorStatus()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'payment_error_status');
    }

    public function getOneclickEmailSettings()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'new_email_order');
    }

    public function getOneclickInvoiceSettings()
    {
        return $this->scopeConfigInterface->getValue(self::ORDER_CONFIGS_ROUTE_ONECLICK.'invoice_settings');
    }
}
