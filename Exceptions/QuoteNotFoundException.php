<?php

namespace Transbank\Webpay\Exceptions;

class QuoteNotFoundException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('El carro indicado no existe.', 0, $previous);
    }
}
