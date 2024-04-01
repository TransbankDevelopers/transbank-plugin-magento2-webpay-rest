<?php
namespace Transbank\Webpay\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

use DateTime;
use DateTimeZone;

class DateHelper {

    /**
     * @param string $utcDate representation of date in UTC format
     *
     * @return string|null date string in localtime representation, `null` if input cannot be transformed
     */
    public static function utcToLocalDate($utcDate): string {
        try {
            $scopeConfig = ObjectManagerHelper::get(ScopeConfigInterface::class);
            $timezone = $scopeConfig->getValue('general/locale/timezone');

            $utcDate = new DateTime($utcDate, new DateTimeZone('UTC'));
            $utcDate->setTimezone(new DateTimeZone($timezone));

            return $utcDate->format('d-m-Y H:i:s P');
        }
        catch (\Exception $e) {
            return null;
        }
    }
}
