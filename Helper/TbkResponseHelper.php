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

    const PAYMENT_TYPE = [
        "VD" => self::PAYMENT_TYPE_DEBIT,
        "VN" => self::PAYMENT_TYPE_CREDIT,
        "VC" => self::PAYMENT_TYPE_CREDIT,
        "SI" => self::PAYMENT_TYPE_CREDIT,
        "S2" => self::PAYMENT_TYPE_CREDIT,
        "NC" => self::PAYMENT_TYPE_CREDIT,
        "VP" => self::PAYMENT_TYPE_PREPAID
    ];

    const PAYMENT_TYPE_CODE = [
        "VD" => "Venta Débito",
        "VN" => "Venta Normal",
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés",
        "VP" => "Venta Prepago"
    ];

    const STATUS_DESCRIPTION =  [
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
     * @param string $paymentType The code of the payment type.
     * @return string The corresponding payment type.
     */
    public static function getPaymentType(string $paymentType): string
    {
        return self::PAYMENT_TYPE[$paymentType] ?? $paymentType;
    }

    /**
     * Get the installment type from the payment type response.
     *
     * @param string $paymentType The code of the installment type.
     * @return string The corresponding installment type.
     */
    public static function getInstallmentType(string $paymentType): string
    {
        return self::PAYMENT_TYPE_CODE[$paymentType] ?? $paymentType;
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
     * Retrieve success message for a transaction
     *
     * @param object|array $transactionResult
     * @param string $product
     * @return string success message
     */
    public static function getSuccessMessage($transactionResult, string $product)
    {

        if (strpos($product, 'click')) {
            $transactionResult = self::getOneclickDetails($transactionResult);
        }

        if (in_array($transactionResult->paymentTypeCode, ['SI', 'S2', 'NC', 'VC'])) {
            $tipoCuotas = self::$paymentTypeCodearray[$transactionResult->paymentTypeCode];
        } else {
            $tipoCuotas = 'Sin cuotas';
        }

        if ($transactionResult->responseCode == 0) {
            $transactionResponse = 'Transacci&oacute;n Aprobada';
        } else {
            $transactionResponse = 'Transacci&oacute;n Rechazada';
        }

        if ($transactionResult->paymentTypeCode == 'VD') {
            $paymentType = 'Débito';
        } elseif ($transactionResult->paymentTypeCode == 'VP') {
            $paymentType = 'Prepago';
        } else {
            $paymentType = 'Crédito';
        }
        $installmentsString = '';
        if ($tipoCuotas != 'Sin cuotas') {
            $installmentsString = "
                <div>
                    • N&uacute;mero de cuotas: <b>{$transactionResult->installmentsNumber}</b>
                </div>
                <div>
                    • Monto Cuota: <b>{$transactionResult->installmentsAmount}</b>
                </div>
            ";
        }

        return "
            <b>Detalles del pago con {$product}</b>
            <div>
                • Respuesta de la Transacci&oacute;n: <b>{$transactionResponse}</b>
            </div>
            <div>
                • C&oacute;digo de la Transacci&oacute;n: <b>{$transactionResult->responseCode}</b>
            </div>
            <div>
                • Monto: <b>$ {$transactionResult->amount}</b>
            </div>
            <div>
                • Order de Compra: <b> {$transactionResult->buyOrder}</b>
            </div>
            <div>
                • Fecha de la Transacci&oacute;n: <b>" . substr(self::utcToLocalDate($transactionResult->transactionDate), 0, 10) . '</b>
            </div>
            <div>
                • Hora de la Transacci&oacute;n: <b>' . substr(self::utcToLocalDate($transactionResult->transactionDate), 11, 8) . "</b>
            </div>
            <div>
                • Tarjeta: <b>**** **** **** {$transactionResult->cardNumber}</b>
            </div>
            <div>
                • C&oacute;digo de autorizacion: <b>{$transactionResult->authorizationCode}</b>
            </div>
            <div>
                • Tipo de Pago: <b>{$paymentType}</b>
            </div>
            <div>
                • Tipo de Cuotas: <b>{$tipoCuotas}</b>
            </div>
            {$installmentsString}
            ";
    }

    public static function getRejectMessage($transactionResult, $product)
    {
        if (strpos($product, 'click')) {
            $transactionResult = self::getOneclickDetails($transactionResult);
            return 'Transacción rechazada con Oneclick Mall' .
                nl2br('• Respuesta de la Transacción: ' . $transactionResult->responseCode . ' ') .
                nl2br('• Monto:$ ' . $transactionResult->amount . ' ') .
                nl2br('• Orden de Compra: ' . $transactionResult->buyOrder . ' ') .
                nl2br('• Fecha de la Transacción: ' . substr(self::utcToLocalDate($transactionResult->transactionDate), 0, 10) . ' ') .
                nl2br('• Hora de la Transacción: ' . substr(self::utcToLocalDate($transactionResult->transactionDate), 11, 8) . ' ') .
                nl2br('• Tarjeta: **** **** **** ' . $transactionResult->cardNumber . '');
        }

        if (isset($transactionResult)) {
            $message = "<h2>Transacci&oacute;n rechazada con {$product}</h2>
                <div>
                    • Respuesta de la Transacci&oacute;n: <b>{$transactionResult->responseCode}</b>
                </div>
                <div>
                    • Monto:<b>$ {$transactionResult->amount}</b>
                </div>
                <div>
                    • Orden de Compra:<b> {$transactionResult->buyOrder}</b>
                </div>
                <div>
                    • Fecha de la Transacci&oacute;n: <b>" . substr(self::utcToLocalDate($transactionResult->transactionDate), 0, 10) . "</b>
                </div>
                <div>
                    • Hora de la Transacci&oacute;n: <b>" . substr(self::utcToLocalDate($transactionResult->transactionDate), 11, 8) . "</b>
                </div>
                <div>
                    • Tarjeta: <b>**** **** **** {$transactionResult->cardNumber}</b>
                </div>
           ";
        } else {
            if ($transactionResult->status == 'ERROR') {
                $error = $transactionResult->status;
                $detail = isset($transactionResult->details[0]) ? $transactionResult->details[0] : 'Sin detalles';
                $message = "<h2>Transacci&oacute;n fallida con {$product}</h2>
                <div>
                    <b>• Respuesta de la Transacci&oacute;n: </b>{$error}
                </div>
                <div>
                    <b>• Mensaje: </b>{$detail}
                </div>";
            } else {
                $message = '<h2>Transacci&oacute;n Fallida</h2>';
            }
        }

        return $message;
    }


    public static function getOneclickDetails($transactionResult)
    {
        $details = $transactionResult->details;
        foreach ($details as $detail) {
            $transactionResult->amount = $detail->amount;
            $transactionResult->authorizationCode = $detail->authorizationCode;
            $transactionResult->buyOrder = $detail->buyOrder;
            $transactionResult->installmentsNumber = $detail->installmentsNumber;
            $transactionResult->paymentTypeCode = $detail->paymentTypeCode;
            $transactionResult->responseCode = $detail->responseCode;
            $transactionResult->status = $detail->status;
        }

        return $transactionResult;
    }

}
