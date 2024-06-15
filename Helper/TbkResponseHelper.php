<?php

namespace Transbank\Webpay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

use DateTime;
use DateTimeZone;

class TbkResponseHelper
{
    const PAYMENT_TYPE_CREDIT = "Crédito";
    const PAYMENT_TYPE_DEBIT = "Débito";
    const PAYMENT_TYPE_PREPAID = "Prepago";

    const PAYMENT_TYPES = [
        "VD" => self::PAYMENT_TYPE_DEBIT,
        "VN" => self::PAYMENT_TYPE_CREDIT,
        "VC" => self::PAYMENT_TYPE_CREDIT,
        "SI" => self::PAYMENT_TYPE_CREDIT,
        "S2" => self::PAYMENT_TYPE_CREDIT,
        "NC" => self::PAYMENT_TYPE_CREDIT,
        "VP" => self::PAYMENT_TYPE_PREPAID
    ];

    const INSTALLMENT_TYPES = [
        "VD" => "Venta Débito",
        "VN" => "Venta Normal",
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés",
        "VP" => "Venta Prepago"
    ];

    const STATUS_DESCRIPTIONS =  [
        'INITIALIZED' => 'Inicializada',
        'AUTHORIZED' => 'Autorizada',
        'REVERSED' => 'Reversada',
        'FAILED' => 'Fallida',
        'NULLIFIED' => 'Anulada',
        'PARTIALLY_NULLIFIED' => 'Parcialmente anulada',
        'CAPTURED' => 'Capturada',
    ];

    /**
     * Get the payment type from its code.
     *
     * @param string $paymentTypeCode The code of the payment type.
     * @return string The corresponding payment type.
     */
    public static function getPaymentType(string $paymentTypeCode): string
    {
        return self::PAYMENT_TYPES[$paymentTypeCode] ?? $paymentTypeCode;
    }

    /**
     * Get the installment type from the payment type response.
     *
     * @param string $paymentTypeCode The code of the installment type.
     * @return string The corresponding installment type.
     */
    public static function getInstallmentType(string $paymentTypeCode): string
    {
        return self::INSTALLMENT_TYPES[$paymentTypeCode] ?? $paymentTypeCode;
    }

    /**
     * Get the transaction status description from response status.
     *
     * @param string $status The code of the transaction status.
     * @return string The description of the corresponding transaction status.
     */
    public static function getStatus(string $status): string
    {
        return self::STATUS_DESCRIPTIONS[$status] ?? $status;
    }


    /**
     * @param string $utcDate representation of date in UTC format
     *
     * @return string|null date string in localtime representation, `null` if input cannot be transformed
     */
    public static function utcToLocalDate($utcDate): string
    {
        try {
            $scopeConfig = ObjectManagerHelper::get(ScopeConfigInterface::class);
            $timezone = $scopeConfig->getValue('general/locale/timezone');

            $utcDate = new DateTime($utcDate, new DateTimeZone('UTC'));
            $utcDate->setTimezone(new DateTimeZone($timezone));

            return $utcDate->format('d-m-Y H:i:s P');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the formatted accounting date from response.
     *
     * @param string $accountingDate The accounting date in 'md' format.
     * @return string The accounting date in 'mm-dd' format.
     */
    public static function getAccountingDate(string $accountingDate): string
    {
        $date = DateTime::createFromFormat('md', $accountingDate);

        if (!$date) {
            return $accountingDate;
        }

        return $date->format('m-d');
    }

    /**
     * Get the CLP formatted amount from an integer value.
     *
     * @param int $amount The integer amount to be formatted.
     * @return string The formatted amount as a string.
     */
    public static function getAmountFormatted(int $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get the common fields formatted for sale receipt.
     *
     * @param object $transactionResponse The transaction response.
     * @return array The formatted common fields.
     */
    private static function getCommonFieldsFormatted(object $transactionResponse): array
    {
        $scopeConfig = ObjectManagerHelper::get(ScopeConfigInterface::class);
        $timezone = $scopeConfig->getValue('general/locale/timezone');

        $utcDate = new DateTime($transactionResponse->transactionDate, new DateTimeZone('UTC'));
        $utcDate->setTimezone(new DateTimeZone($timezone));

        $buyOrder = $transactionResponse->buyOrder;
        $cardNumber = "**** **** **** {$transactionResponse->cardNumber}";
        $transactionDate = $utcDate->format('d-m-Y');
        $transactionTime = $utcDate->format('H:i:s');

        return [
            'buyOrder' => $buyOrder,
            'cardNumber' => $cardNumber,
            'transactionDate' => $transactionDate,
            'transactionTime' => $transactionTime
        ];
    }

    /**
     * Get the formatted response for Webpay transactions.
     *
     * @param object $transactionResponse The response object for Webpay transactions.
     * @return array The formatted response fields.
     */
    public static function getWebpayFormattedResponse(object $transactionResponse): array
    {
        $commonFields = self::getCommonFieldsFormatted($transactionResponse);

        $amount = self::getAmountFormatted($transactionResponse->amount);
        $paymentType = self::getPaymentType($transactionResponse->paymentTypeCode);
        $installmentType = self::getInstallmentType($transactionResponse->paymentTypeCode);
        $installmentAmount = self::getAmountFormatted($transactionResponse->installmentsAmount ?? 0);

        $webpayFields = [
            'amount' => $amount,
            'authorizationCode' => $transactionResponse->authorizationCode,
            'paymentType' => $paymentType,
            'installmentType' => $installmentType,
            'installmentNumber' => $transactionResponse->installmentsNumber,
            'installmentAmount' => $installmentAmount
        ];

        return array_merge($commonFields, $webpayFields);
    }

    /**
     * Get the formatted response for Oneclick transactions.
     *
     * @param object $transactionResponse The response object for Oneclick transactions.
     * @return array The formatted response fields.
     */
    public static function getOneclickFormattedResponse(object $transactionResponse): array
    {
        $commonFields = self::getCommonFieldsFormatted($transactionResponse);
        $detail = $transactionResponse->details[0];

        $amount = self::getAmountFormatted($detail->amount);
        $paymentType = self::getPaymentType($detail->paymentTypeCode);
        $installmentType = self::getInstallmentType($detail->paymentTypeCode);
        $installmentAmount = self::getAmountFormatted($detail->installmentsAmount ?? 0);

        $oneclickFields = [
            'amount' => $amount,
            'authorizationCode' => $detail->authorizationCode,
            'paymentType' => $paymentType,
            'installmentType' => $installmentType,
            'installmentNumber' => $detail->installmentsNumber,
            'installmentAmount' => $installmentAmount
        ];

        return array_merge($commonFields, $oneclickFields);
    }
}
