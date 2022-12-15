define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'transbank_webpay',
                component: 'Transbank_Webpay/js/view/payment/method-renderer/webpay-method'
            },
            {
                type: 'transbank_oneclick',
                component: 'Transbank_Webpay/js/view/payment/method-renderer/oneclick-method'
            }
        );
        // View logic goes here!
        return Component.extend({});
    }
);

