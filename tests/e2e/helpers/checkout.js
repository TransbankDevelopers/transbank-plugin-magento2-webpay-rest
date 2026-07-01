const CUSTOMER = {
    email: process.env.CUSTOMER_EMAIL,
    password: process.env.CUSTOMER_PASSWORD,
};

export async function login(page) {
    await page.goto("/customer/account/login/");
    await page.locator("#email").fill(CUSTOMER.email);
    await page.locator("#password").fill(CUSTOMER.password);
    await page.locator("#send2").click();
    await page.waitForLoadState("domcontentloaded", { timeout: 15_000 });
}

export async function addProductToCart(page) {
    await page.goto("/producto-demo.html");
    await page.waitForLoadState("domcontentloaded");
    await page
        .locator("#product-addtocart-button")
        .waitFor({ state: "visible", timeout: 15_000 });
    await page.locator("#product-addtocart-button").click();
    await page.waitForTimeout(2_000);
}

export async function goThroughCheckoutWithWebpay(page) {
    await page.goto("/checkout/");
    await page.waitForLoadState("networkidle", { timeout: 30_000 });

    const shippingStep = page.locator("#checkout-step-shipping");
    await shippingStep.waitFor({ state: "visible", timeout: 20_000 });

    const firstNameField = page.locator('[name="firstname"]');

    if (
        (await firstNameField.count()) > 0 &&
        (await firstNameField.isVisible())
    ) {
        await firstNameField.fill("Cliente");
        await page.locator('[name="lastname"]').fill("Demo");
        await page.locator('[name="street[0]"]').fill("Av. Providencia 1234");
        await page.locator('[name="city"]').fill("Santiago");
        await page.locator('[name="postcode"]').fill("7500000");
        await page.locator('[name="country_id"]').selectOption("CL");
        await page.locator('[name="telephone"]').fill("+56912345678");
    }

    const shippingMethodRow = page
        .locator(".table-checkout-shipping-method tbody tr input[type='radio']")
        .first();

    if ((await shippingMethodRow.count()) > 0) {
        await shippingMethodRow.click();
    }

    await page.locator(".button.action.continue.primary").click();

    await page
        .locator("#checkout-step-payment")
        .waitFor({ state: "visible", timeout: 20_000 });
    await page.waitForLoadState("networkidle", { timeout: 10_000 });
    await page.getByRole("button", { name: "Next" }).click();

    const webpayRadio = page.locator("#transbank_webpay");
    await webpayRadio.waitFor({ state: "visible", timeout: 10_000 });
    await webpayRadio.check();

    await page.getByRole("button", { name: "Place Order" }).click();
    await page.waitForURL(/webpay3gint\.transbank\.cl|tbk\.cl/, {
        timeout: 45_000,
    });
}
