<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Transbank\Webpay\Exceptions\InvalidRequestException;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Infrastructure\Lock\MySqlNamedLock;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;

class Commit extends Action
{
    private const GENERIC_ERROR = 'No fue posible completar la inscripción de la tarjeta.';
    private const SUCCESS_MESSAGE = 'Tarjeta inscrita exitosamente.';
    private const FLOW_NORMAL = 'normal';
    private const FLOW_ABORTED = 'aborted';
    private const FLOW_INVALID = 'invalid';
    private const LOCK_PREFIX = 'transbank_private_oneclick_commit_';

    private $customerSession;
    private $configProvider;
    private $inscriptionService;
    private $lock;
    private $logger;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        ConfigProvider $configProvider,
        OneclickInscriptionService $inscriptionService,
        MySqlNamedLock $lock,
        PluginLogger $logger
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->configProvider = $configProvider;
        $this->inscriptionService = $inscriptionService;
        $this->lock = $lock;
        $this->logger = $logger;
    }

    public function execute()
    {
        $inscription = null;
        $ownedInscription = false;

        try {
            return $this->handleRequest(
                (array) $this->getRequest()->getParams(),
                $inscription,
                $ownedInscription
            );
        } catch (\Throwable $exception) {
            $this->failWaitingInscription($inscription, $ownedInscription);

            return $this->handleException($exception);
        }
    }

    private function handleRequest(array $request, &$inscription, bool &$ownedInscription)
    {
        $flow = $this->getInscriptionFlow($request);

        if ($flow === self::FLOW_INVALID) {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }

        $this->logger->logInfo('Procesando retorno de inscripción Oneclick.', [
            'flow' => $flow,
            'method' => $this->getRequest()->getMethod(),
            'has_order' => $this->hasValue($request['TBK_ORDEN_COMPRA'] ?? null),
            'has_session' => $this->hasValue($request['TBK_ID_SESION'] ?? null),
        ]);

        $token = $this->getToken($request);

        if ($flow === self::FLOW_ABORTED) {
            return $this->handleAbortedFlow($token, $inscription, $ownedInscription);
        }

        return $this->processInscription($token, $inscription, $ownedInscription);
    }

    /**
     * Normal and timeout returns are indistinguishable: both contain only TBK_TOKEN.
     * Aborted returns contain TBK_TOKEN plus TBK_ORDEN_COMPRA and/or TBK_ID_SESION.
     */
    private function getInscriptionFlow(array $request): string
    {
        if (!$this->hasValue($request['TBK_TOKEN'] ?? null)) {
            return self::FLOW_INVALID;
        }

        if (
            $this->hasValue($request['TBK_ORDEN_COMPRA'] ?? null) ||
            $this->hasValue($request['TBK_ID_SESION'] ?? null)
        ) {
            return self::FLOW_ABORTED;
        }

        return self::FLOW_NORMAL;
    }

    private function processInscription(string $token, &$inscription, bool &$ownedInscription)
    {
        $lockKey = $this->getLockKey($token);
        $lockAcquired = false;

        try {
            $this->assertAuthenticated();
            $inscription = $this->getOwnedInscription($token);
            $ownedInscription = true;
            $lockAcquired = $this->lock->acquire($lockKey);

            if (!$lockAcquired) {
                throw new TransbankException('No se pudo serializar la finalización de inscripción.');
            }

            $inscription = $this->inscriptionService->getByToken($token);

            return $this->resolveInscription($inscription, $token);
        } finally {
            $this->releaseLock($lockKey, $lockAcquired);
        }
    }

    private function handleAbortedFlow(string $token, &$inscription, bool &$ownedInscription)
    {
        $this->assertAuthenticated();
        $inscription = $this->getOwnedInscription($token);
        $ownedInscription = true;

        if ($inscription->getStatus() === OneclickInscriptionData::PAYMENT_STATUS_WATING) {
            $this->inscriptionService->setInscriptionAsFailed($inscription);
        }

        $this->logger->logInfo('Inscripción Oneclick abortada por el usuario.', [
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);

        return $this->redirectWithError();
    }

    private function resolveInscription(OneclickInscriptionData $inscription, string $token)
    {
        $status = $inscription->getStatus();

        if ($status === OneclickInscriptionData::PAYMENT_STATUS_WATING) {
            return $this->finishWaitingInscription($inscription, $token);
        }

        if ($status === OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
            return $this->redirectWithSuccess();
        }

        return $this->redirectWithError();
    }

    private function finishWaitingInscription(OneclickInscriptionData $inscription, string $token)
    {
        $result = (new TransbankSdkWebpayRest(
            $this->configProvider->getPluginConfigOneclick()
        ))->finishInscription($token);

        if ($this->inscriptionService->resolveInscriptionFinishResult($inscription, $result)) {
            return $this->redirectWithSuccess();
        }

        return $this->redirectWithError();
    }

    private function getOwnedInscription(string $token): OneclickInscriptionData
    {
        $inscription = $this->inscriptionService->getByToken($token);

        if (!$this->inscriptionService->isOwnedByCustomer(
            $inscription->getUserId(),
            $this->customerSession->getCustomerId()
        )) {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }

        return $inscription;
    }

    private function assertAuthenticated(): void
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }
    }

    private function getToken(array $request): string
    {
        $token = $request['TBK_TOKEN'] ?? null;

        if (!$this->hasValue($token)) {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }

        return trim($token);
    }

    private function hasValue($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function getLockKey(string $token): string
    {
        return self::LOCK_PREFIX . substr(hash('sha256', $token), 0, 24);
    }

    private function releaseLock(string $lockKey, bool $lockAcquired): void
    {
        if (!$lockAcquired) {
            return;
        }

        try {
            $this->lock->release($lockKey);
        } catch (\Throwable $exception) {
            $this->logger->logError('Error al liberar lock de inscripción Oneclick.', [
                'exception' => get_class($exception),
                'message' => 'Private inscription lock release failed.',
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
        }
    }

    private function handleException(\Throwable $exception)
    {
        $this->logFailure($exception);

        return $this->redirectWithError();
    }

    private function redirectWithSuccess()
    {
        $this->messageManager->addSuccessMessage(__(self::SUCCESS_MESSAGE));

        return $this->redirectToPrivateCards();
    }

    private function redirectWithError()
    {
        $this->messageManager->addErrorMessage(__(self::GENERIC_ERROR));

        return $this->redirectToPrivateCards();
    }

    private function redirectToPrivateCards()
    {
        return $this->resultRedirectFactory->create()->setPath('customer/oneclick/index');
    }

    private function failWaitingInscription($inscription, bool $ownedInscription): void
    {
        if (
            !$ownedInscription || !$inscription instanceof OneclickInscriptionData ||
            $inscription->getStatus() !== OneclickInscriptionData::PAYMENT_STATUS_WATING
        ) {
            return;
        }

        try {
            $this->inscriptionService->setInscriptionAsFailed($inscription);
        } catch (\Throwable $exception) {
            $this->logger->logError('No se pudo actualizar la inscripción fallida.', [
                'exception' => get_class($exception),
                'message' => 'Failed inscription status update failed.',
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
        }
    }

    private function logFailure(\Throwable $exception): void
    {
        $this->logger->logError('Error al completar inscripción privada.', [
            'exception' => get_class($exception),
            'message' => 'Private inscription commit failed.',
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);
    }
}
