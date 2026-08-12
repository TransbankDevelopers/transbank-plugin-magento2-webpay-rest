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
    private const INVALID_CARD_MESSAGE = "La tarjeta inscrita indicada es inválida.";
    private const UNAUTHORIZED_MESSAGE = "No posee permisos suficientes para eliminar esta tarjeta inscrita.";
    private const DELETE_SUCCESS_MESSAGE = "Tarjeta inscrita eliminada exitosamente.";
    private const DELETE_ERROR_MESSAGE = "Error al eliminar tarjeta inscrita.";
    private const DELETE_EXCEPTION_MESSAGE = "Error al eliminar tarjeta inscrita, contacta con soporte.";

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

            if ($inscriptionId === false) {
                $this->messageManager->addErrorMessage(__(self::INVALID_CARD_MESSAGE));
                return $this->redirectToReferer();
            }

            if (!$this->isAuthorizedToDelete($inscriptionId)) {
                $this->messageManager->addErrorMessage(__(self::UNAUTHORIZED_MESSAGE));
                return $this->redirectToReferer();
            }

            $this->logger->logInfo("Recibida petición para eliminar tarjeta inscrita.", [
                'inscription_id' => $inscriptionId,
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);

            $inscription = $this->oneclickInscriptionService->getById($inscriptionId);

            if (!$inscription) {
                $this->messageManager->addErrorMessage(__(self::DELETE_ERROR_MESSAGE));
                return $this->redirectToReferer();
            }

            $username = $inscription->getUsername();
            $tbkUser = $inscription->getTbkUser();

            $deleteTransbankResponse = $this->deleteTransbankInscription($username, $tbkUser);

            if (!$deleteTransbankResponse) {
                $this->messageManager->addErrorMessage(__(self::DELETE_ERROR_MESSAGE));
                return $this->redirectToReferer();
            }

            $inscription = $this->deleteLocalInscription($inscription->getId());

            $this->logger->logInfo("Tarjeta inscrita eliminada exitosamente.", [
                'inscription_id' => $inscription->getId(),
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);

            $this->messageManager->addSuccessMessage(__(self::DELETE_SUCCESS_MESSAGE));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__(self::DELETE_EXCEPTION_MESSAGE));
        }

        return $this->redirectToReferer();
    }

    private function isAuthorizedToDelete(int $inscriptionId): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        $inscription = $this->oneclickInscriptionService->getById($inscriptionId);

        return $this->oneclickInscriptionService->isOwnedByCustomer(
            $inscription->getUserId(),
            $this->customerSession->getCustomerId()
        );
    }

    private function deleteLocalInscription(int $inscriptionId): \Transbank\Webpay\Model\OneclickInscriptionData
    {
        return $this->oneclickInscriptionService->setInscriptionAsDeleted($inscriptionId);
    }

    private function deleteTransbankInscription($username, $tbkUser): bool
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
        return $transbankSdkWebpay->deleteInscription($username, $tbkUser);
    }

    private function redirectToReferer()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
