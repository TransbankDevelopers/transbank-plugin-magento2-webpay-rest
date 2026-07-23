<?php

namespace Transbank\Webpay\Exceptions;

class OrderNotFoundException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('La orden indicada no existe.', 0, $previous);
    }
}
