<?php

namespace Transbank\Webpay\Model\Config;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface {
    const CONFIG_ROUTE = 'payment/transbank_webpay/security_parameters/';

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface) {

        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    public function getConfig() {
        return [
            'pluginConfigWebpay' => array(
                'createTransactionUrl' => 'transaction/createwebpay'
            )
        ];
    }

    public function getPluginConfig() {
        $config = array(
            'ENVIRONMENT' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'environment'),
            'COMMERCE_CODE' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'commerce_code'),
			'API_KEY' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'api_key'),
			'URL_RETURN' => 'checkout/transaction/commitwebpay',
			'URL_FINAL' => 'checkout/transaction/commitwebpay',
            'ECOMMERCE' => 'magento',
            'new_order_status' => $this->getOrderPendingStatus(),
            'payment_successful_status' => $this->getOrderSuccessStatus(),
            'payment_error_status' => $this->getOrderErrorStatus(),
        );
        return $config;
    }

    public function getOrderPendingStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'new_order_status');
    }

    public function getOrderSuccessStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'payment_successful_status');
    }

    public function getOrderErrorStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'payment_error_status');
    }
}
