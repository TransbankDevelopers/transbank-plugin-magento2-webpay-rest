<?php
namespace Transbank\Webpay\Model\Config;

class Environment implements \Magento\Framework\Option\ArrayInterface {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return [['value' => 'INTEGRACION', 'label' => __('INTEGRACION')],
                ['value' => 'PRODUCCION', 'label' => __('PRODUCCION')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        return ['INTEGRACION' => __('INTEGRACION'),
                'PRODUCCION' => __('PRODUCCION')];
    }
}
