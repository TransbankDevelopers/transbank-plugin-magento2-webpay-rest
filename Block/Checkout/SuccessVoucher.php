<?php

namespace Transbank\Webpay\Block\Checkout;

use Magento\Framework\View\Element\Template;

class SuccessVoucher extends Template
{
    protected $response;

    public function setResponse(array $response)
    {
        $this->response = $response;
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
