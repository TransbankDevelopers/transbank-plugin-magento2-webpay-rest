<?php

namespace Transbank\Webpay\Model\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Model\Repository\OneclickInscriptionDataRepository;

/**
 * Class OneclickInscriptionService
 * Business rules around OneClick inscriptions that depend on the current customer session.
 */
class OneclickInscriptionService
{
    protected $oneclickInscriptionDataRepository;
    protected $customerSession;

    /**
     * Constructor
     *
     * @param OneclickInscriptionDataRepository
     * @param CustomerSession
     */
    public function __construct(
        OneclickInscriptionDataRepository $oneclickInscriptionDataRepository,
        CustomerSession $customerSession
    ) {
        $this->oneclickInscriptionDataRepository = $oneclickInscriptionDataRepository;
        $this->customerSession = $customerSession;
    }

    /**
     * Get the active inscriptions of the customer currently logged in, or an empty array for guests.
     *
     * @return array
     */
    public function getInscriptionsForCurrentCustomer(): array
    {
        $customerId = $this->customerSession->getCustomer()->getId();

        if (!isset($customerId)) {
            return [];
        }

        return $this->oneclickInscriptionDataRepository->getActiveInscriptionsByCustomerId((int) $customerId);
    }
}
