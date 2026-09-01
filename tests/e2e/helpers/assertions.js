import { expect } from "@playwright/test";

export function hasFatalError(content) {
    return (
        content.includes("Fatal error") ||
        content.includes("500 Internal Server Error")
    );
}

export async function isOrderConfirmation(page) {
    const content = await page.content();
    return content.includes("tbk_voucher");
}

export async function waitForOrderConfirmation(page, timeout) {
    await expect
        .poll(() => isOrderConfirmation(page), {
            timeout,
            intervals: [1_000],
            message: "Waiting for order confirmation content"
        })
        .toBe(true);
}

export function isPaymentError(url) {
    return url.includes("checkout/cart");
}

export async function expectOrderConfirmation(page) {
    const url = page.url();
    const content = await page.content();

    expect(hasFatalError(content), `Page has fatal errors — url: ${url}`).toBe(
        false,
    );
    expect(
        content.includes("tbk_voucher"),
        `Expected order confirmation — got: ${url}`,
    ).toBe(true);
}

export async function expectPaymentError(page) {
    const url = page.url();
    const content = await page.content();

    expect(hasFatalError(content), `Page has fatal errors — url: ${url}`).toBe(
        false,
    );
    expect(isPaymentError(url), `Expected payment error — url: ${url}`).toBe(
        true,
    );
}

export async function expectValidResponse(page, label) {
    const url = page.url();
    const content = await page.content();
    const fatal = hasFatalError(content);
    const confirmation = content.includes("tbk_voucher");
    const paymentErr = isPaymentError(url);

    expect(fatal, `${label} has fatal errors — url: ${url}`).toBe(false);
    expect(
        confirmation || paymentErr,
        `${label} must show confirmation or error — url: ${url}`,
    ).toBe(true);

    return { confirmation, paymentErr };
}
