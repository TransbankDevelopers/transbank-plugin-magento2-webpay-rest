<?php

namespace Transbank\Webpay\Model\Service;

use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\Repository\OneclickInscriptionDataRepository;

/**
 * Class OneclickInscriptionService
 * Business rules and data-access orchestration for OneClick inscriptions.
 */
class OneclickInscriptionService
{
    protected $oneclickInscriptionDataRepository;

    /**
     * Constructor
     *
     * @param OneclickInscriptionDataRepository
     */
    public function __construct(
        OneclickInscriptionDataRepository $oneclickInscriptionDataRepository
    ) {
        $this->oneclickInscriptionDataRepository = $oneclickInscriptionDataRepository;
    }

    /**
     * Get a OneclickInscriptionData by id
     *
     * @param int $id The inscription id
     *
     * @throws OneclickInscriptionNotFoundException When no inscription matches the given id
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
     * @throws OneclickInscriptionNotFoundException When no inscription matches the given token
     *
     * @return OneclickInscriptionData
     */
    public function getByToken(string $token): OneclickInscriptionData
    {
        return $this->oneclickInscriptionDataRepository->getByToken($token);
    }

    /**
     * Get the active inscriptions of a given customer, or an empty array for guests.
     *
     * @param $customerId The customer id, or null for guests
     *
     * @return array
     */
    public function getActiveInscriptionsByCustomerId($customerId): array
    {
        if (empty($customerId)) {
            return [];
        }

        return $this->oneclickInscriptionDataRepository->getActiveInscriptionsByCustomerId((int) $customerId);
    }

    /**
     * Generate the next incremental username for a customer, based on their previous inscriptions.
     *
     * @param $customerId The customer id, or null for guests
     *
     * @return string
     */
    public function generateInscriptionUsername($customerId)
    {
        $inscriptions = $this->getActiveInscriptionsByCustomerId($customerId);

        if (empty($inscriptions)) {
            $username = 'U_' . $customerId . '_1';
        } else {
            $last_inscription = end($inscriptions);
            $last_username = $last_inscription['username'];
            $username_parts = explode('_', $last_username);
            $last_correlative = intval(end($username_parts));
            $new_correlative = $last_correlative + 1;
            $username = 'U_' . $customerId . '_' . $new_correlative;
        }

        return $username;
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
     * @param int $inscriptionId The inscription id
     *
     * @throws OneclickInscriptionNotFoundException When no inscription matches the given id
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
     * Persist a OneclickInscriptionData record.
     *
     * @param OneclickInscriptionData $inscriptionData The inscription record to persist
     *
     * @return void
     */
    public function save(OneclickInscriptionData $inscriptionData): void
    {
        $this->oneclickInscriptionDataRepository->save($inscriptionData);
    }

    /**
     * Interpret the SDK's finishInscription() result and update the inscription accordingly.
     *
     * @param OneclickInscriptionData $inscription The inscription being resolved
     * @param $inscriptionResult The SDK response from finishInscription()
     *
     * @return bool True if the inscription finished successfully, false otherwise
     */
    public function resolveInscriptionFinishResult(OneclickInscriptionData $inscription, $inscriptionResult): bool
    {
        $inscription->setMetadata(json_encode($inscriptionResult));

        if (isset($inscriptionResult->tbkUser) && isset($inscriptionResult->responseCode) && $inscriptionResult->responseCode == 0) {
            $inscription->setStatus(OneclickInscriptionData::PAYMENT_STATUS_SUCCESS);
            $inscription->setResponseCode($inscriptionResult->responseCode);
            $inscription->setTbkUser($inscriptionResult->tbkUser);
            $inscription->setAuthorizationCode($inscriptionResult->authorizationCode);
            $inscription->setCardType($inscriptionResult->cardType);
            $inscription->setCardNumber($inscriptionResult->cardNumber);

            $this->oneclickInscriptionDataRepository->save($inscription);

            return true;
        } else {
            $inscription->setStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);

            if (isset($inscriptionResult->responseCode)) {
                $inscription->setResponseCode($inscriptionResult->responseCode);
            }

            $this->oneclickInscriptionDataRepository->save($inscription);

            return false;
        }
    }
}
