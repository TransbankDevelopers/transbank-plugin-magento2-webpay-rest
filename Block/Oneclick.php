<?php

namespace Transbank\Webpay\Block;

use Magento\Framework\View\Element\Template;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class Oneclick extends Template
{

    protected $oneclickInscriptionService;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        OneclickInscriptionService $oneclickInscriptionService,
        array $data = [])
    {
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        parent::__construct($context, $data);
    }

    public function getCards()
    {
        return $this->oneclickInscriptionService->getInscriptionsForCurrentCustomer();
    }

    public function getDeleteAction()
    {
        return $this->getUrl('checkout/oneclick/delete', ['_secure' => true]);
    }
}