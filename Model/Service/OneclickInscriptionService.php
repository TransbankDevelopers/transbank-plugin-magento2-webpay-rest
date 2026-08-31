<?php

namespace Transbank\Webpay\Model\Service;

use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Exceptions\TransbankException;
use Transbank\Webpay\Infrastructure\Lock\MySqlNamedLock;
use Transbank\Webpay\Model\Config\ConfigProvider;
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
    private ConfigProvider $configProvider;
    private MySqlNamedLock $lock;
    private TransbankSdkWebpayRest $transbankSdkWebpayRest;

    /**
     * Constructor
     *
     * @param OneclickInscriptionDataRepository $oneclickInscriptionDataRepository
     * @param ConfigProvider $configProvider
     * @param MySqlNamedLock $lock
     * @param TransbankSdkWebpayRest $transbankSdkWebpayRest
     */
    public function __construct(
        OneclickInscriptionDataRepository $oneclickInscriptionDataRepository,
        ConfigProvider $configProvider,
        MySqlNamedLock $lock,
        TransbankSdkWebpayRest $transbankSdkWebpayRest
    ) {
        $this->oneclickInscriptionDataRepository = $oneclickInscriptionDataRepository;
        $this->configProvider = $configProvider;
        $this->transbankSdkWebpayRest = $transbankSdkWebpayRest;
        $this->lock = $lock;
    }

    /**
     * Start a private OneClick inscription: acquire the serialization lock, call the SDK,
     * validate the response and persist the inscription.
     *
     * @param int $customerId The authenticated customer id
     * @param string $email The customer email
     * @param string $returnUrl The URL Transbank redirects to after the inscription form
     *
     * @throws TransbankException When the lock cannot be acquired or the SDK response is invalid
     *
     * @return array{token: string, webpayUrl: string}
     */
    public function startPrivateInscription(int $customerId, string $email, string $returnUrl): array
    {
        $lockKey = 'transbank_private_oneclick_add_' . $customerId;

        if (!$this->lock->acquire($lockKey)) {
            throw new TransbankException('No se pudo serializar la inscripción.');
        }

        try {
            $username = $this->generateInscriptionUsername($customerId);
            $response = $this->transbankSdkWebpayRest
                ->createInscription($username, $email, $returnUrl);
            $token = $response['token'] ?? null;
            $webpayUrl = $response['urlWebpay'] ?? null;

            if (!$this->isValidResponseValue($token) || !$this->isValidHttpsUrl($webpayUrl)) {
                throw new TransbankException('Respuesta inválida al iniciar inscripción.');
            }

            $config = $this->configProvider->getPluginConfigOneclick();
            $this->oneclickInscriptionDataRepository->create([
                'status' => OneclickInscriptionData::PAYMENT_STATUS_WATING,
                'token' => $token,
                'username' => $username,
                'email' => $email,
                'user_id' => $customerId,
                'environment' => $config['ENVIRONMENT'] ?? null,
                'commerce_code' => $config['COMMERCE_CODE'] ?? null,
                'metadata' => json_encode(['source' => 'private'], JSON_THROW_ON_ERROR),
            ]);

            return ['token' => $token, 'webpayUrl' => $webpayUrl];
        } finally {
            $this->lock->release($lockKey);
        }
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
     *
     * @return OneclickInscriptionData The updated inscription
     */
    public function delete(OneclickInscriptionData $inscription): OneclickInscriptionData
    {
        $this->transbankSdkWebpayRest->deleteInscription($inscription->getUsername(), $inscription->getTbkUser());

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

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function isValidResponseValue($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param mixed $url
     *
     * @return bool
     */
    private function isValidHttpsUrl($url): bool
    {
        return $this->isValidResponseValue($url) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
