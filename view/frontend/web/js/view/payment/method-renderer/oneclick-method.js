define([
    "jquery",
    "Magento_Checkout/js/view/payment/default",
    "Magento_Checkout/js/action/place-order",
    "Magento_Checkout/js/action/select-payment-method",
    "Magento_Customer/js/model/customer",
    "Magento_Checkout/js/checkout-data",
    "Magento_Checkout/js/model/payment/additional-validators",
    "mage/url",
    "Magento_Checkout/js/model/quote",
    "Magento_Checkout/js/action/set-payment-information",
], function (
    $,
    Component,
    placeOrderAction,
    selectPaymentMethodAction,
    customer,
    checkoutData,
    additionalValidators,
    urlBuilder,
    quote,
    setPaymentInformationAction
) {
    "use strict";

    return Component.extend({
        defaults: {
            template: "Transbank_Webpay/payment/oneclick",
        },

        getCode: function () {
            return "transbank_oneclick";
        },

        placeOrder: function (data, event) {
            placeOneclickOrderFunction(event, additionalValidators, this);
        },

        getPlaceOrderDeferredObject: function () {
            return $.when(
                placeOrderAction(this.getData(), this.messageContainer)
            );
        },

        getCardList: function () {
            const storedInscriptions =
                window.checkoutConfig.oneclick_inscriptions;
            let inscriptions = [];

            inscriptions = storedInscriptions.map((inscription) => {
                let last_digits = inscription.card_number.substr(
                    inscription.card_number.length - 4
                );

                return {
                    key: `${inscription.card_type} terminada en ${last_digits}`,
                    value: inscription.id,
                };
            });

            return inscriptions;
        },

        getOneclickConfig: function () {
            const grandTotal = window.checkoutConfig.totalsData.grand_total;
            const oneclickMaxAmount =
                window.checkoutConfig.totalsData.oneclick_max_amount;

            return oneclickMaxAmount < grandTotal;
        },
    });
});

function checkTransaction(result) {
    if (result != undefined && result.token != undefined) {
        let form = jQuery(
            '<form action="' +
                result.urlWebpay +
                "?TBK_TOKEN=" +
                result.token +
                '" method="post">' +
                "</form>"
        );
        jQuery("body").append(form);
        form.submit();
    } else {
        alert("Error al crear transacción");
    }
}

function authorizeTransaction(selected_inscription) {
    let url =
        window.checkoutConfig.pluginConfigOneclick.authorizeTransactionUrl;

    let form = jQuery(
        '<form action="' +
            url +
            '" method="post">' +
            '<input type="hidden" name="inscription" value="' +
            selected_inscription +
            '" />' +
            "</form>"
    );
    jQuery("body").append(form);
    form.submit();
}

function handleOneclickTransaction(self, selectedInscription) {
    self.afterPlaceOrder();
    if (!selectedInscription) {
        let url =
            window.checkoutConfig.pluginConfigOneclick.createTransactionUrl;
        console.log(url);
        jQuery.getJSON(url, checkTransaction);
    } else {
        authorizeTransaction(selectedInscription);
    }
}

function placeOneclickOrderFunction(event, additionalValidators, context) {
    let self = context;

    const selected_inscription = jQuery(
        "#" + context.getCode() + "_payment_profile_id"
    ).val();

    if (event) {
        event.preventDefault();
    }

    if (!context.validate() || !additionalValidators.validate()) {
        return false;
    }
    context.isPlaceOrderActionAllowed(false);

    context
        .getPlaceOrderDeferredObject()
        .fail(function () {
            self.isPlaceOrderActionAllowed(true);
        })
        .done(function () {
            handleOneclickTransaction(self, selected_inscription);
        })
        .always(function () {
            self.isPlaceOrderActionAllowed(true);
            jQuery("body").loader("show");
        });
    return true;
}
