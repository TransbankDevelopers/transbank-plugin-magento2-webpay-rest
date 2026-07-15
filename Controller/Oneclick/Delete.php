<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\Config\ConfigProvider;

class Delete extends Action
{
    private const INVALID_CARD_MESSAGE = "La tarjeta inscrita indicada es inválida.";
    private const UNAUTHORIZED_MESSAGE = "No posee permisos suficientes para eliminar esta tarjeta inscrita.";
    private const DELETE_SUCCESS_MESSAGE = "Tarjeta inscrita eliminada exitosamente.";
    private const DELETE_ERROR_MESSAGE = "Error al eliminar tarjeta inscrita.";
    private const DELETE_EXCEPTION_MESSAGE = "Error al eliminar tarjeta inscrita, contacta con soporte.";

    protected $configProvider;
    protected $oneclickInscriptionService;
    protected $customerSession;

    public function __construct(
        Context $context,
        OneclickInscriptionService $oneclickInscriptionService,
        ConfigProvider $configProvider,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->customerSession = $customerSession;
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

            if (!$this->customerSession->isLoggedIn()) {
                $this->messageManager->addErrorMessage(__(self::UNAUTHORIZED_MESSAGE));
                return $this->redirectToReferer();
            }

            $inscription = $this->oneclickInscriptionService->getById($inscriptionId);
            $customerId = $this->customerSession->getCustomerId();

            if (!$this->oneclickInscriptionService->isOwnedByCustomer($inscription->getUserId(), $customerId)) {
                $this->messageManager->addErrorMessage(__(self::UNAUTHORIZED_MESSAGE));
                return $this->redirectToReferer();
            }

            $oneclickInscriptionData = $this->oneclickInscriptionService->setInscriptionAsDeleted($inscriptionId);
            $config = $this->configProvider->getPluginConfigOneclick();
            $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
            $username = $oneclickInscriptionData->getUsername();
            $tbkUser = $oneclickInscriptionData->getTbkUser();
            $response = $transbankSdkWebpay->deleteInscription($username, $tbkUser);

            if ($response === true) {
                $this->messageManager->addSuccessMessage(__(self::DELETE_SUCCESS_MESSAGE));
            } else {
                $this->messageManager->addErrorMessage(__(self::DELETE_ERROR_MESSAGE));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__(self::DELETE_EXCEPTION_MESSAGE));
        }

        return $this->redirectToReferer();
    }

    private function redirectToReferer()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
