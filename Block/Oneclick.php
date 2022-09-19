<?php

namespace Transbank\Webpay\Block;
 
use Magento\Framework\View\Element\Template;

class Oneclick extends Template
{
 
    protected $getInscriptions;
 
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Transbank\Webpay\Helper\Inscriptions $getInscriptions,
        array $data = [])
    {
        $this->getInscriptions = $getInscriptions;
        parent::__construct($context, $data);
    }
 
    public function getCards()
    {
        return $this->getInscriptions->getInscriptions();
    }

    public function getDeleteAction()
    {
        return $this->getUrl('checkout/oneclick/delete', ['_secure' => true]);
    }
 
}