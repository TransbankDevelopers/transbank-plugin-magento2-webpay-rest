<?php

namespace Transbank\Webpay\Model\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Model\OneclickInscriptionData;
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

    /**
     * Get a OneclickInscriptionData by id
     *
     * @param int $id The inscription id
     *
     * @return OneclickInscriptionData
     */
    public function getById(int $id): OneclickInscriptionData
    {
        return $this->oneclickInscriptionDataRepository->getById($id);
    }

    /**
     * Get a OneclickInscriptionData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @return OneclickInscriptionData
     */
    public function getByToken(string $token): OneclickInscriptionData
    {
        return $this->oneclickInscriptionDataRepository->getByToken($token);
    }

    /**
     * Validate that the customer currently logged in is the same one who registered the card.
     *
     * @param OneclickInscriptionData $inscriptionData The card inscription data
     *
     * @return bool True if the payer matches the card inscription, false otherwise
     */
    public function isPayerMatchingInscription(OneclickInscriptionData $inscriptionData): bool
    {
        $customerData = $this->customerSession->getCustomerData();
        $customerId = $customerData->getId();
        $inscriptionUserId = $inscriptionData->getUserId();

        return $customerId == $inscriptionUserId;
    }

    /**
     * Re-flag an inscription as failed and persist it.
     *
     * @param OneclickInscriptionData $inscription The inscription being re-flagged
     *
     * @return void
     */
    public function setInscriptionAsFailed(OneclickInscriptionData $inscription): void
    {
        $inscription->setStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);
        $this->oneclickInscriptionDataRepository->save($inscription);
    }

    /**
     * Generate the next incremental username for a customer, based on their previous inscriptions.
     *
     * @param $customerId
     *
     * @return string
     */
    public function generateInscriptionUsername($customerId)
    {
        $inscriptions = $this->getInscriptionsForCurrentCustomer();

        if (empty($inscriptions)){
            $username = 'U_'.$customerId.'_1';
        } else {
            $last_inscription = end($inscriptions);
            $last_username = $last_inscription['username'];
            $last_correlative = intval(substr($last_username, -1));
            $new_correlative = $last_correlative + 1;
            $username = 'U_'.$customerId.'_'.$new_correlative;
        }

        return $username;
    }
}
