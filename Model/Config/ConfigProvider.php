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
			'MODO' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'environment'),
			'PRIVATE_KEY' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'private_key'),
			'PUBLIC_CERT' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'public_cert'),
			'WEBPAY_CERT' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'webpay_cert'),
            'COMMERCE_CODE' => $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'commerce_code'),
			'URL_RETURN' => 'checkout/transaction/commitwebpay',
			'URL_FINAL' => 'checkout/transaction/commitwebpay',
            'ECOMMERCE' => 'magento',
            'order_status' => $this->getOrderPendingStatus(),
            'sucefully_pay' => $this->getOrderSuccessStatus(),
            'error_pay' => $this->getOrderErrorStatus(),
        );
        return $config;
    }

    public function getOrderPendingStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'order_status');
    }

    public function getOrderSuccessStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'sucefully_pay');
    }

    public function getOrderErrorStatus() {
        return $this->scopeConfigInterface->getValue(self::CONFIG_ROUTE.'error_pay');
    }
}
