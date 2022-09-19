<?php
namespace Transbank\Webpay\Helper;
 
use Magento\Framework\Data\Form\Element\AbstractElement;
 
class Disable extends \Magento\Config\Block\System\Config\Form\Field {

    protected function _getElementHtml(AbstractElement $element) {
        $orderStatus = $this->_scopeConfig->getValue('payment/transbank_webpay/general_parameters/payment_successful_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $oneclickOrderStatus = $this->_scopeConfig->getValue('payment/transbank_oneclick/general_parameters/payment_successful_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if ($orderStatus == 'processing' || $oneclickOrderStatus == 'processing') {
            $element->setDisabled(false);
        } else {
            $element->setDisabled(true);
        }

        return $element->getElementHtml();
    }
}