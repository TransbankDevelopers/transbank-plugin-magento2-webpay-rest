<?php

namespace Transbank\Webpay\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class Oneclick extends Template
{

    protected $oneclickInscriptionService;
    protected $customerSession;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        OneclickInscriptionService $oneclickInscriptionService,
        CustomerSession $customerSession,
        array $data = [])
    {
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getCards()
    {
        return $this->oneclickInscriptionService->getActiveInscriptionsByCustomerId($this->customerSession->getCustomer()->getId());
    }

    public function getDeleteAction()
    {
        return $this->getUrl('checkout/oneclick/delete', ['_secure' => true]);
    }
}
