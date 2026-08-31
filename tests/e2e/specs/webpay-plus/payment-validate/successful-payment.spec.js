import { test, expect } from "@playwright/test";
import {
    login,
    addProductToCart,
    goThroughCheckoutWithWebpay,
} from "../../../helpers/checkout.js";
import {
    fillCardAndAuthenticate,
    continueToCommerce,
} from "../../../helpers/webpay-form.js";
import {
    expectOrderConfirmation,
    isOrderConfirmation,
} from "../../../helpers/assertions.js";

test.describe("Webpay Plus — Normal payment flow", () => {
    test("Authorized payment shows order confirmation", async ({ page }) => {
        console.log("═══ START: Normal payment flow ═══");

        await test.step("Login", async () => {
            await login(page);
        });

        await test.step("Add product to cart", async () => {
            await addProductToCart(page);
        });

        await test.step("Complete checkout with Webpay Plus", async () => {
            await goThroughCheckoutWithWebpay(page);
        });

        await test.step("Complete payment form on Transbank", async () => {
            await fillCardAndAuthenticate(page);
        });

        await test.step("Return to commerce and verify confirmation", async () => {
            await continueToCommerce(page);
            await page.waitForURL(/checkout\/transaction\/commitwebpay/, {
                timeout: 45_000
            });
            await expect
                .poll(() => isOrderConfirmation(page), {
                    timeout: 45_000,
                    intervals: [1_000],
                    message: "Waiting for order confirmation content"
                })
                .toBe(true);
            await expectOrderConfirmation(page);
            console.log(`[INTERCEPTOR] Confirmation: url=${page.url()}`);
        });

        console.log("═══ END: Normal payment flow ═══");
    });
});
