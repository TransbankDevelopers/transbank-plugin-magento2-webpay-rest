<?php

namespace Transbank\Webpay\Model;

class Webpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'transbank_webpay';
    const PRODUCT_NAME = 'webpay_plus';

    /**
     * Payment code.
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Array of currency support.
     */
    protected $_supportedCurrencyCodes = ['CLP'];

    protected $_isGateway = true;
    protected $_canCapture = true;
    //protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize = true;

    /**
     * Availability for currency.
     *
     * @param string $currencyCode
     *
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return true;
    }

}
