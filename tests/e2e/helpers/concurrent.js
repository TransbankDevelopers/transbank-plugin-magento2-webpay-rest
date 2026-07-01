import {
    login,
    addProductToCart,
    goThroughCheckoutWithWebpay,
} from "./checkout.js";
import { fillCardAndAuthenticate } from "./webpay-form.js";
import { isPaymentError } from "./assertions.js";

const isReturnUrl = (url) => {
    return url.toString().includes("/checkout/transaction/commitwebpay");
};

export async function runCheckoutFlow(page) {
    await login(page);
    await addProductToCart(page);
    await goThroughCheckoutWithWebpay(page);
    await fillCardAndAuthenticate(page);
}

export async function holdReturnRequests(context) {
    let releaseReturns;
    let returnsReleased = false;
    const returnsCanContinue = new Promise((resolve) => {
        releaseReturns = resolve;
    });
    const commerceReturns = [];

    await context.route(isReturnUrl, async (route) => {
        const request = route.request();
        const requestUrl = request.url();
        let getUrl = requestUrl;

        if (request.method() === "POST") {
            const postData = request.postData() ?? "";
            const params = new URLSearchParams(postData);
            const token = params.get("token_ws");
            if (token && !requestUrl.includes("token_ws=")) {
                getUrl = `${requestUrl}?token_ws=${encodeURIComponent(token)}`;
            }
        }

        commerceReturns.push(getUrl);
        console.log(
            `[INTERCEPTOR] Return #${commerceReturns.length} intercepted: ${getUrl}`,
        );

        if (!returnsReleased) {
            await returnsCanContinue;
        }

        await route.continue();
    });

    return {
        commerceReturns,
        release() {
            returnsReleased = true;
            releaseReturns();
        },
    };
}

export async function hasNavigatedPastValidation(page) {
    try {
        const url = page.url();
        if (url.includes("checkout/cart")) return true;
        const content = await page.content();
        return content.includes("tbk_voucher");
    } catch {
        return false;
    }
}

export async function hasErrorContent(page) {
    try {
        return isPaymentError(page.url());
    } catch {
        return false;
    }
}
