<?php

namespace Transbank\Webpay\Model\Config;

class ConfigInvoice implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'default', 'label' => __('Default Magento')], ['value' => 'transbank', 'label' => __('Al confirmar el pago')]];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array
     */
    public function toArray()
    {
        return ['default' => __('Default Magento'), 'transbank'  => __('Al confirmar el pago')];
    }
}
