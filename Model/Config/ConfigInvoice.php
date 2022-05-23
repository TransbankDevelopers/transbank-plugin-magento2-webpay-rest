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
        return [['value' => 'transbank', 'label' => __('Al confirmar el pago')],
            ['value' => 'default', 'label' => __('Default Magento')] ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array
     */
    public function toArray()
    {
        return ['transbank'  => __('Al confirmar el pago'),
            'default' => __('Default Magento')];
    }
}
