<?php
namespace Transbank\Webpay\Model;

class Webpay extends \Magento\Payment\Model\Method\AbstractMethod {

    const CODE = 'transbank_webpay';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Array of currency support
     */
    protected $_supportedCurrencyCodes = array('CLP');

    protected $_isGateway = true;
    protected $_canCapture = true;
    //protected $_canCapturePartial = true;
    protected $_canRefund = true;
    //protected $_canRefundInvoicePartial = true;
    protected $_canAuthorize = true;

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }
        return true;
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {

        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        //$metadata = $payment->getData()['additional_information'][\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS];
        //$payment->setTransactionId($metadata['externalUniqueNumber']);
        //$payment->setIsTransactionClosed(0);

        return $this;
    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount) {

        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {

        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        return $this;
    }
}
