<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Transbank\Webpay\Exceptions\InvalidRequestException;
use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Infrastructure\Lock\MySqlNamedLock;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Exceptions\TransbankException;

class Commit extends Action
{
    private const GENERIC_ERROR = 'No fue posible completar la inscripción de la tarjeta.';
    private const SUCCESS_MESSAGE = 'Tarjeta inscrita exitosamente.';
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
            $this->assertAuthenticated();
            $token = $this->getToken();
            $inscription = $this->inscriptionService->getByToken($token);

            if (!$this->inscriptionService->isOwnedByCustomer(
                $inscription->getUserId(),
                $this->customerSession->getCustomerId()
            )) {
                throw new InvalidRequestException(self::GENERIC_ERROR);
            }
            $ownedInscription = true;

            $lockKey = self::LOCK_PREFIX . substr(hash('sha256', $token), 0, 24);
            if (!$this->lock->acquire($lockKey)) {
                throw new TransbankException('No se pudo serializar la finalización de inscripción.');
            }

            try {
                $inscription = $this->inscriptionService->getByToken($token);
                $status = $inscription->getStatus();

                if ($status === OneclickInscriptionData::PAYMENT_STATUS_WATING) {
                    $result = (new TransbankSdkWebpayRest(
                        $this->configProvider->getPluginConfigOneclick()
                    ))->finishInscription($token);

                    if ($this->inscriptionService->resolveInscriptionFinishResult($inscription, $result)) {
                        $this->messageManager->addSuccessMessage(__(self::SUCCESS_MESSAGE));
                    } else {
                        $this->messageManager->addErrorMessage(__(self::GENERIC_ERROR));
                    }
                } elseif ($status === OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
                    $this->messageManager->addSuccessMessage(__(self::SUCCESS_MESSAGE));
                } else {
                    $this->messageManager->addErrorMessage(__(self::GENERIC_ERROR));
                }
            } finally {
                $this->lock->release($lockKey);
            }
        } catch (\Throwable $e) {
            $this->failWaitingInscription($inscription, $ownedInscription);
            $this->logger->logError('Error al completar inscripción privada.', [
                'exception' => get_class($e),
                'message' => 'Private inscription commit failed.',
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
            $this->messageManager->addErrorMessage(__(self::GENERIC_ERROR));
        }

        return $this->resultRedirectFactory->create()->setPath('customer/oneclick/index');
    }

    private function assertAuthenticated(): void
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }
    }

    private function getToken(): string
    {
        $token = $this->getRequest()->getParam('TBK_TOKEN');
        if (!is_string($token) || trim($token) === '') {
            throw new InvalidRequestException(self::GENERIC_ERROR);
        }

        return $token;
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
}
