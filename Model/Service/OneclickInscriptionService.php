<?php

namespace Transbank\Webpay\Model\Service;

use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\Repository\OneclickInscriptionDataRepository;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;

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
     * Determine whether an inscription owner id matches the given customer.
     *
     * @param int|string|null $inscriptionOwnerId The user_id stored on the inscription
     * @param int|string|null $customerId The id of the customer attempting the operation
     *
     * @return bool True if the inscription is owned by the customer, false otherwise
     */
    public function isOwnedByCustomer($inscriptionOwnerId, $customerId): bool
    {
        $customerId = (int) $customerId;

        return $customerId !== 0 && (int) $inscriptionOwnerId === $customerId;
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
     * Generate a unique Oneclick username for an authenticated customer.
     *
     * @param int $customerId The authenticated customer id (must be > 0)
     *
     * @return string
     */
    public function generateInscriptionUsername(int $customerId): string
    {
        $integratorCode = 'mg';
        $uidByteLength = 8;

        if ($customerId <= 0) {
            throw new \InvalidArgumentException('El id de cliente es inválido.');
        }

        $uid = bin2hex(random_bytes($uidByteLength));

        return sprintf('%s:%d:%s', $integratorCode, $customerId, $uid);
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
     * Delete an inscription at Transbank and mark it as deleted locally.
     *
     * @param OneclickInscriptionData $inscription The inscription to delete
     * @param array $config The Oneclick SDK configuration
     *
     * @return OneclickInscriptionData The updated inscription
     */
    public function delete(OneclickInscriptionData $inscription, array $config): OneclickInscriptionData
    {
        $sdk = new TransbankSdkWebpayRest($config);
        $sdk->deleteInscription($inscription->getUsername(), $inscription->getTbkUser());

        return $this->oneclickInscriptionDataRepository->update($inscription, [
            'status' => OneclickInscriptionData::INSCRIPTION_STATUS_DELETED,
        ]);
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
