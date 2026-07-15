<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Magento\Customer\Model\Session as CustomerSession;

class Delete extends Action
{
    protected $configProvider;
    protected $oneclickInscriptionService;
    protected $resultPageFactory;
    protected $customerSession;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        OneclickInscriptionService $oneclickInscriptionService,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);
        $this->configProvider = $configProvider;
        $this->resultPageFactory = $resultPageFactory;
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->customerSession = $customerSession;
    }

    public function execute()
    {
        try {
            $data = (array)$this->getRequest()->getParams();
            $inscriptionId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($inscriptionId !== false && $this->customerSession->isLoggedIn()) {
                $inscription = $this->oneclickInscriptionService->getById($inscriptionId);
                $customerId = $this->customerSession->getCustomerId();

                if ($this->oneclickInscriptionService->isOwnedByCustomer($inscription->getUserId(), $customerId)) {
                    $oneclickInscriptionData = $this->oneclickInscriptionService->setInscriptionAsDeleted($inscriptionId);
                    $username = $oneclickInscriptionData->getUsername();
                    $tbkUser = $oneclickInscriptionData->getTbkUser();

                    $config = $this->configProvider->getPluginConfigOneclick();

                    $transbankSdkWebpay = new TransbankSdkWebpayRest($config);

                    $response = $transbankSdkWebpay->deleteInscription($username, $tbkUser);

                    if (is_bool($response) && $response) {
                        $this->messageManager->addSuccessMessage(__("Tarjeta inscrita eliminada exitosamente."));
                    } else {
                        $this->messageManager->addErrorMessage(__("Error al eliminar tarjeta inscrita."));
                    }
                } else {
                    $this->messageManager->addErrorMessage(__("No posee permisos suficientes para eliminar esta tarjeta inscrita."));
                }
            } else {
                $this->messageManager->addErrorMessage(__("Tarjeta inscrita no encontrada"));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__("Error al eliminar tarjeta inscrita, contacta con soporte."));
        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
