<?php

namespace Transbank\Webpay\Model\Config;

class Environment implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'TEST', 'label' => __('Integración (Pruebas)')],
            ['value' => 'LIVE', 'label' => __('Producción')], ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array
     */
    public function toArray()
    {
        return ['TEST' => __('Integración (Pruebas)'),
            'LIVE'     => __('Producción'), ];
    }
}
