<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\Config\ConfigProvider;

class Delete extends Action implements HttpPostActionInterface
{
    private const INVALID_CARD_MESSAGE = "La tarjeta indicada es inválida.";
    private const UNAUTHORIZED_MESSAGE = "No posee permisos suficientes para eliminar esta tarjeta.";
    private const DELETE_SUCCESS_MESSAGE = "Tarjeta eliminada exitosamente.";
    private const DELETE_ERROR_MESSAGE = "Error al eliminar tarjeta.";
    private const DELETE_EXCEPTION_MESSAGE = "Error al eliminar tarjeta, contacta con al comercio para recibir asistencia.";

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
            $this->validateRequest($inscriptionId);
            $this->deleteInscription($inscriptionId);
        } catch (\Throwable $e) {
            $this->logger->logError('Error al eliminar tarjeta inscrita.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
            $this->messageManager->addErrorMessage(__(self::DELETE_EXCEPTION_MESSAGE));
        }

        return $this->redirectToReferer();
    }

    private function validateRequest($inscriptionId): void
    {
        if ($inscriptionId === false) {
            throw new \InvalidArgumentException(self::INVALID_CARD_MESSAGE);
        }

        if (!$this->customerSession->isLoggedIn()) {
            throw new \LogicException(self::UNAUTHORIZED_MESSAGE);
        }

        $inscription = $this->oneclickInscriptionService->getById($inscriptionId);
        $isOwnedByCustomer = $this->oneclickInscriptionService->isOwnedByCustomer(
            $inscription->getUserId(),
            $this->customerSession->getCustomerId()
        );

        if (!$isOwnedByCustomer) {
            throw new \LogicException(self::UNAUTHORIZED_MESSAGE);
        }
    }

    private function deleteInscription(int $inscriptionId): void
    {
        $this->logger->logInfo("Recibida petición para eliminar tarjeta inscrita.", [
            'inscription_id' => $inscriptionId,
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);

        $inscription = $this->oneclickInscriptionService->getById($inscriptionId);
        $this->deleteTransbankInscription($inscription->getUsername(), $inscription->getTbkUser());
        $this->oneclickInscriptionService->setInscriptionAsDeleted($inscription->getId());

        $this->logger->logInfo("Tarjeta inscrita eliminada exitosamente.", [
            'inscription_id' => $inscription->getId(),
            'customer_id' => $this->customerSession->getCustomerId(),
        ]);

        $this->messageManager->addSuccessMessage(__(self::DELETE_SUCCESS_MESSAGE));
    }

    private function deleteTransbankInscription($username, $tbkUser): void
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $transbankSdkWebpay = new TransbankSdkWebpayRest($config);

        if (!$transbankSdkWebpay->deleteInscription($username, $tbkUser)) {
            throw new \RuntimeException(self::DELETE_ERROR_MESSAGE);
        }
    }

    private function redirectToReferer()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
