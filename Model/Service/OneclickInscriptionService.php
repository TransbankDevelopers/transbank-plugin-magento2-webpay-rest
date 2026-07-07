<?php

namespace Transbank\Webpay\Model\Service;

use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Transbank\Webpay\Model\Repository\OneclickInscriptionDataRepository;

/**
 * Class OneclickInscriptionService
 * Business rules around OneClick inscriptions that depend on the current customer session.
 */
class OneclickInscriptionService
{
    protected $oneclickInscriptionDataRepository;
    protected $customerSession;
    protected $oneclickInscriptionDataFactory;

    /**
     * Constructor
     *
     * @param OneclickInscriptionDataRepository
     * @param CustomerSession
     * @param OneclickInscriptionDataFactory
     */
    public function __construct(
        OneclickInscriptionDataRepository $oneclickInscriptionDataRepository,
        CustomerSession $customerSession,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
    ) {
        $this->oneclickInscriptionDataRepository = $oneclickInscriptionDataRepository;
        $this->customerSession = $customerSession;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
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
     * Mark an inscription as deleted and persist it.
     *
     * Note: if the given id does not match any row, the underlying model stays "new"
     * and save() performs an INSERT instead of failing — this preserves the existing
     * (buggy) behavior, it is not corrected here.
     *
     * @param int $inscriptionId The inscription id
     *
     * @return OneclickInscriptionData The updated inscription
     */
    public function setInscriptionAsDeleted(int $inscriptionId): OneclickInscriptionData
    {
        $inscription = $this->getById($inscriptionId);
        $inscription->setStatus(OneclickInscriptionData::PAYMENT_STATUS_DELETED);

        return $this->oneclickInscriptionDataRepository->save($inscription);
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

    /**
     * Create and persist a new OneclickInscriptionData record.
     *
     * @param $status
     * @param $token
     * @param $username
     * @param $email
     * @param $user_id
     * @param $order_id
     * @param $environment
     * @param $commerce_code
     * @param $metadata
     */
    public function createInscriptionRecord(
        $status,
        $token,
        $username,
        $email,
        $user_id,
        $order_id,
        $environment,
        $commerce_code,
        $metadata
    ) {
        $oneclickInscriptionData = $this->oneclickInscriptionDataFactory->create();
        $oneclickInscriptionData->setData([
            'status'          => $status,
            'token'          => $token,
            'username'       => $username,
            'email'          => $email,
            'user_id'        => $user_id,
            'order_id'       => $order_id,
            'environment'    => $environment,
            'commerce_code'  => $commerce_code,
            'metadata'       => $metadata,
        ]);
        $this->oneclickInscriptionDataRepository->save($oneclickInscriptionData);
    }
}
