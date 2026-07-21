<?php

namespace Transbank\Webpay\Exceptions;

class OneclickInscriptionNotFoundException extends \Exception
{
    public function __construct(string $message = 'La tarjeta inscrita indicada no existe.')
    {
        parent::__construct($message);
    }
}
