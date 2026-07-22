<?php

namespace Transbank\Webpay\Exceptions;

class OrderNotFoundException extends \Exception
{
    public function __construct(string $message = 'La orden indicada no existe.')
    {
        parent::__construct($message);
    }
}
