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
    "Magento_Checkout/js/action/set-payment-information"
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
            template: "Transbank_Webpay/payment/oneclick"
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
                    value: inscription.id
                };
            });

            return inscriptions;
        },

        getOneclickConfig: function () {
            const grandTotal = window.checkoutConfig.totalsData.grand_total;
            const oneclickMaxAmount =
                window.checkoutConfig.totalsData.oneclick_max_amount;

            return oneclickMaxAmount < grandTotal;
        }
    });
});

function checkTransaction(result) {
    if (result != undefined && result.token != undefined) {
        const url = new URL(result.urlWebpay);
        const params = new URLSearchParams();
        params.append("TBK_TOKEN", result.token);

        const form = document.createElement("form");
        form.setAttribute("action", `${url}?${params.toString()}`);
        form.setAttribute("method", "post");

        document.body.appendChild(form);

        form.submit();
    } else {
        alert("Error al crear transacción");
    }
}

function authorizeTransaction(selected_inscription) {
    const url =
        window.checkoutConfig.pluginConfigOneclick.authorizeTransactionUrl;

    const form = document.createElement("form");
    form.setAttribute("action", url);
    form.setAttribute("method", "post");

    const input = document.createElement("input");
    input.setAttribute("type", "hidden");
    input.setAttribute("name", "inscription");
    input.setAttribute("value", selected_inscription);

    const formKeyInput = document.createElement("input");
    formKeyInput.setAttribute("type", "hidden");
    formKeyInput.setAttribute("name", "form_key");
    formKeyInput.setAttribute("value", jQuery.cookie("form_key"));

    form.appendChild(input);
    form.appendChild(formKeyInput);
    document.body.appendChild(form);

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
