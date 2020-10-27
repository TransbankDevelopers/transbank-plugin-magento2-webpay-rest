<?php
namespace Transbank\Webpay\Model\Config;

class OrderStatus implements \Magento\Framework\Option\ArrayInterface {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        return [['value' => 'processing', 'label' => __('processing')],
                ['value' => 'pending_payment', 'label' => __('pending_payment')],
                ['value' => 'payment_review', 'label' => __('payment_review')],
                ['value' => 'complete', 'label' => __('complete')],
                ['value' => 'canceled', 'label' => __('canceled')],
                ['value' => 'closed', 'label' => __('closed')]];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        return ['processing' => __('processing'),
                'pending_payment' => __('pending_payment'),
                'payment_review' => __('payment_review'),
                'complete' => __('complete'),
                'canceled' => __('canceled'),
                'closed' => __('closed')];
    }

}
