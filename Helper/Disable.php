<?php

namespace Transbank\Webpay\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Disable extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $orderStatus = $this->_scopeConfig->getValue(
            'payment/transbank_webpay/payment_successful_status',
            ScopeInterface::SCOPE_STORE
        );
        $oneclickOrderStatus = $this->_scopeConfig->getValue(
            'payment/transbank_oneclick/payment_successful_status',
            ScopeInterface::SCOPE_STORE
        );

        if ($orderStatus == 'processing' || $oneclickOrderStatus == 'processing') {
            $element->setDisabled(false);
        } else {
            $element->setDisabled(true);
        }

        return $element->getElementHtml();
    }
}
