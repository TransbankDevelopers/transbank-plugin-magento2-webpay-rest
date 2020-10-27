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
        'Magento_Checkout/js/model/full-screen-loader',
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
        fullScreenLoader,
        setPaymentInformationAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Transbank_Webpay/payment/webpay'
            },

            getCode: function () {
                return 'transbank_webpay';
            },
            getTitle: function () {
                return "Transbank Webpay";
            },
            placeOrder: function (data, event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function () {
                                self.afterPlaceOrder();

                                var url = window.checkoutConfig.pluginConfigWebpay.createTransactionUrl;

                                if (quote.guestEmail) {
                                    url += '?guestEmail=' + encodeURIComponent(quote.guestEmail);
                                }

                                $.getJSON(url, function (result) {
                                    if (result != undefined && result.token_ws != undefined) {
                                        var form = $('<form action="' + result.url + '" method="post">' +
                                            '<input type="text" name="token_ws" value="' + result.token_ws + '" />' +
                                            '</form>');
                                        $('body').append(form);
                                        form.submit();
                                    } else {
                                        alert('Error al crear transacción');
                                    }
                                });
                            }
                        ).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                                fullScreenLoader.stopLoader();
                            }
                        );
                    return true;
                }
                return false;

            },

            getPlaceOrderDeferredObject: function () {
                return $.when(
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },

        })
    }
);
