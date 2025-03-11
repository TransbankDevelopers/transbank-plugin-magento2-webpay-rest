<?php

namespace Transbank\Webpay\Helper;

class TransactionHelper {
    private const STORE_PREFIX = 'mg';
    private const BUY_ORDER_MAX_LENGTH = 26;
    private const DEFAULT_RANDOM_LENGTH = 8;
    private const BYTES_TO_HEX_RATIO = 2;
    private const SEPARATOR_CHAR_COUNT = 2;

     /**
     * Generates a random hexadecimal string of a given length.
     *
     * @param int $length Desired length of the hexadecimal string.
     * @return string Random hexadecimal string.
     */
    private static function generateRandomComponent(int $length): string {
        return bin2hex(openssl_random_pseudo_bytes(intdiv($length, self::BYTES_TO_HEX_RATIO)));
    }

    /**
     * Generates a unique identifier for a purchase order.
     *
     * Format: "{prefix}-{random}-{orderId}"
     *
     * - `prefix`: Store prefix (e.g., 'mg' for Magento, 'wc' for WooCommerce, 'ps' for PrestaShop).
     * - `orderId`: Unique order identifier in the store.
     * - `random`: Configurable random component to prevent collisions.
     * - Maximum of 26 characters due to API constraints.
     *
     * @param string $prefix Store prefix.
     * @param int|string $orderId Unique order identifier.
     * @param int $randomLength Length of the random component.
     * @return string Order identifier with a maximum of 26 characters.
     */
    private static function generateBuyOrderBase(
        string $prefix, 
        int|string $orderId, 
        int $randomLength = self::DEFAULT_RANDOM_LENGTH
    ): string {
        $orderIdStr = (string)$orderId;
        $maxRandomLength = self::BUY_ORDER_MAX_LENGTH - (strlen($prefix) + strlen($orderIdStr) 
         + self::SEPARATOR_CHAR_COUNT);
        $random = self::generateRandomComponent(min($randomLength, $maxRandomLength));
        return "{$prefix}-{$random}-{$orderIdStr}";
    }

    /**
     * Generates a standard purchase order identifier using the default store prefix.
     *
     * @param int|string $orderId Unique order identifier.
     * @param int $randomLength Length of the random component.
     * @return string Order identifier with a maximum of 26 characters.
     */
    public static function generateBuyOrder($orderId, $randomLength = self::DEFAULT_RANDOM_LENGTH) {
        $storePrefix = self::STORE_PREFIX;
        return self::generateBuyOrderBase($storePrefix, $orderId, $randomLength);
    }

    /**
     * Generates a child purchase order identifier using a modified prefix.
     *
     * The prefix is adjusted by appending "-child" to the default store prefix.
     *
     * @param int|string $orderId Unique order identifier.
     * @param int $randomLength Length of the random component.
     * @return string Child order identifier with a maximum of 26 characters.
     */
    public static function generateChildBuyOrder($orderId, $randomLength = self::DEFAULT_RANDOM_LENGTH) {
        $storePrefix = self::STORE_PREFIX;
        return self::generateBuyOrderBase("{$storePrefix}-child", $orderId, $randomLength);
    }

}

