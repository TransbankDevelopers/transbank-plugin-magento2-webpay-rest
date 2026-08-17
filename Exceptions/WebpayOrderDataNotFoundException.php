<?php

namespace Transbank\Webpay\Exceptions;

class WebpayOrderDataNotFoundException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('No se encontró el registro de la transacción Webpay.', 0, $previous);
    }
}
