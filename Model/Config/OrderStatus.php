<?php

namespace Transbank\Webpay\Model\Config;

class OrderStatus implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 'processing', 'label' => __('Processing')],
            ['value' => 'pending_payment', 'label' => __('Pending Payment')],
            ['value' => 'payment_review', 'label' => __('Payment Review')],
            ['value' => 'complete', 'label' => __('Complete')],
            ['value' => 'canceled', 'label' => __('Canceled')],
            ['value' => 'closed', 'label' => __('Closed')], ];
    }

    /**
     * Get options in "key-value" format.
     *
     * @return array
     */
    public function toArray()
    {
        return ['processing'  => __('Processing'),
            'pending_payment' => __('Pending Payment'),
            'payment_review'  => __('payment_review'),
            'complete'        => __('Complete'),
            'canceled'        => __('Canceled'),
            'closed'          => __('Closed'), ];
    }
}
