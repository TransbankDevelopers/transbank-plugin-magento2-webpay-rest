<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Exceptions\InvalidRequestException;

class Delete extends Action implements HttpPostActionInterface
{
    private const INVALID_CARD_MESSAGE = "La tarjeta indicada es inválida.";
    private const UNAUTHORIZED_MESSAGE = "No posee permisos suficientes para eliminar esta tarjeta.";
    private const DELETE_SUCCESS_MESSAGE = "Tarjeta eliminada exitosamente.";
    private const DELETE_ERROR_MESSAGE = "Error al eliminar tarjeta, contacta al comercio para recibir asistencia.";

    protected $configProvider;
    protected $oneclickInscriptionService;
    protected $customerSession;
    private $logger;

    public function __construct(
        Context $context,
        OneclickInscriptionService $oneclickInscriptionService,
        ConfigProvider $configProvider,
        CustomerSession $customerSession,
        PluginLogger $logger
    ) {
        parent::__construct($context);
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $data = (array) $this->getRequest()->getParams();
            $inscriptionId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $inscription = $this->validateRequest($inscriptionId);
            $this->deleteInscription($inscription);
        } catch (InvalidRequestException $e) {
            $this->logger->logInfo('Error al eliminar tarjeta inscrita.', [
                'message' => $e->getMessage(),
                'customer_id' => $this->customerSession->getCustomerId(),
                'exception' => get_class($e),
            ]);
            $this->messageManager->addErrorMessage(__(self::INVALID_CARD_MESSAGE));
        } catch (\Throwable $e) {
            $this->logger->logError('Error al eliminar tarjeta inscrita.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
            $this->messageManager->addErrorMessage(__(self::DELETE_ERROR_MESSAGE));
        }

        return $this->redirectToReferer();
    }

    private function validateRequest($inscriptionId): OneclickInscriptionData
    {
        if ($inscriptionId === false) {
            throw new InvalidRequestException(self::INVALID_CARD_MESSAGE);
        }

        if (!$this->customerSession->isLoggedIn()) {
            throw new InvalidRequestException(self::UNAUTHORIZED_MESSAGE);
        }

        $inscription = $this->oneclickInscriptionService->getById($inscriptionId);

        if (!$this->oneclickInscriptionService->isOwnedByCustomer(
            $inscription->getUserId(),
            $this->customerSession->getCustomerId()
        )) {
            throw new InvalidRequestException(self::UNAUTHORIZED_MESSAGE);
        }

        return $inscription;
    }

    private function deleteInscription(OneclickInscriptionData $inscription): void
    {
        $this->logger->logInfo("Recibida petición para eliminar tarjeta inscrita.", [
            'inscription_id' => $inscription->getId(),
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);

        $this->oneclickInscriptionService->delete($inscription, $this->configProvider->getPluginConfigOneclick());

        $this->logger->logInfo("Tarjeta inscrita eliminada exitosamente.", [
            'inscription_id' => $inscription->getId(),
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);

        $this->messageManager->addSuccessMessage(__(self::DELETE_SUCCESS_MESSAGE));
    }

    private function redirectToReferer()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
