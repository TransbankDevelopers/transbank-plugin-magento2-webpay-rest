<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class Delete extends Action
{
    protected $configProvider;
    protected $oneclickInscriptionService;
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        OneclickInscriptionService $oneclickInscriptionService,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ) {
        parent::__construct($context);
        $this->configProvider = $configProvider;
        $this->resultPageFactory = $resultPageFactory;
        $this->oneclickInscriptionService = $oneclickInscriptionService;
    }

    public function execute()
    {
        try {
            $data = (array)$this->getRequest()->getParams();
            if ($data) {
                $inscriptionId = $data['id'];
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
