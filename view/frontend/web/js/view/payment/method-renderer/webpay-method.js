define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/set-payment-information'
    ],
    function ($,
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        customer,
        checkoutData,
        additionalValidators,
        url,
        quote,
        setPaymentInformationAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Transbank_Webpay/payment/webpay'
            },

            getCode: function () {
                return 'transbank_webpay';
            },

            placeOrder: function (data, event) {
                placeOrderFunction(event, additionalValidators, this, quote);
            },

            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            }

        })
    }
);

function submitForm (result) {
    if (result != undefined && result.token_ws != undefined) {
        const form = document.createElement('form');
        form.setAttribute('action', result.url);
        form.setAttribute('method', 'post');

        const input = document.createElement('input');
        input.setAttribute('type', 'hidden');
        input.setAttribute('name', 'token_ws');
        input.setAttribute('value', result.token_ws);

        form.appendChild(input);
        document.body.appendChild(form);

        form.submit();
    } else {
        alert('Error al crear transacción');
    }
}

function handleTransaction(self, quote){
    self.afterPlaceOrder();

    let url = window.checkoutConfig.pluginConfigWebpay.createTransactionUrl;

    if (quote.guestEmail) {
        url += '?guestEmail=' + encodeURIComponent(quote.guestEmail);
    }

    jQuery.getJSON(url, submitForm);
}

function placeOrderFunction(event, additionalValidators, context, quote) {
    let self = context;

    if (event) {
        event.preventDefault();
    }

    if (!context.validate() || !additionalValidators.validate()) {
        return false;
    }

    context.isPlaceOrderActionAllowed(false);

    context.getPlaceOrderDeferredObject()
        .fail(
            function () {
                self.isPlaceOrderActionAllowed(true);
            }
        ).done(
            function () {
                handleTransaction(self, quote);
            }
        ).always(
            function () {
                self.isPlaceOrderActionAllowed(true);
                jQuery('body').loader('show');
            }
        );
    return true;
}
