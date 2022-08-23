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
        urlBuilder,
        quote,
        setPaymentInformationAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Transbank_Webpay/payment/oneclick'
            },

            getCode: function () {
                return 'transbank_oneclick';
            },

            placeOrder: function (data, event) {
                var self = this;

                const selected_inscription = jQuery('#'+this.getCode()+'_payment_profile_id').val();

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

                                if (!selected_inscription) {
                                    var url = window.checkoutConfig.pluginConfigOneclick.createTransactionUrl;
                                    $.getJSON(url, function (result) {
                                        if (result != undefined && result.token != undefined) {
                                            var form = $('<form action="' + result.urlWebpay + '?TBK_TOKEN=' + result.token + '" method="post">' +
                                                '</form>');
                                            $('body').append(form);
                                            form.submit();
                                        } else {
                                            alert('Error al crear transacción');
                                        }
                                    });
                                } else {
                                    var url = window.checkoutConfig.pluginConfigOneclick.confirmTransactionUrl;
                                    console.log(`:: Charge ${selected_inscription}`);

                                    $.post(url, {
                                        inscription: selected_inscription
                                    }, function (result) {
                                        console.log(result);
                                        if (result.status == 'success') {
                                            window.location.href = '/checkout/cart/#payment';
                                            // window.location.reload();
                                        } else {
                                            alert('Error al autorizar la compra');
                                        }
                                    });
                                }
                                
                            }
                        ).always(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                                $('body').loader('show');
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
    
            getCardList: function() {
                const storedInscriptions = window.checkoutConfig.oneclick_inscriptions;
                var inscriptions = [];

                inscriptions = storedInscriptions.map(inscription => {
                    var last_digits = inscription.card_number.substr(inscription.card_number.length - 4);

                    return {
                        key: `${inscription.card_type} terminada en ${last_digits}`,
                        value: inscription.id,
                    }
                });

                return inscriptions;
            },
    

        });
    }
);
