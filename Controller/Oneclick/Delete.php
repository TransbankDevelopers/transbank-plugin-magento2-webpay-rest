<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Action\Action;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;

class Delete extends Action
{
    protected $configProvider;
    protected $oneclickInscriptionDataFactory;
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    )
    {
        parent::__construct($context);
        $this->configProvider = $configProvider;
        $this->resultPageFactory = $resultPageFactory;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
    }

    public function execute()
    {
        try {
            $data = (array)$this->getRequest()->getParams();
            if ($data) {
                $inscriptionId = $data['id'];
                list($username, $tbkUser, $OneclickInscriptionData) = $this->getOneclickInscriptionData($inscriptionId);

                $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_DELETED);
                $OneclickInscriptionData->save();

                $config = $this->configProvider->getPluginConfigOneclick();

                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);

                $response = $transbankSdkWebpay->deleteInscription($username, $tbkUser);

                if ($response->success) {
                    $this->messageManager->addSuccessMessage(__("Tarjeta inscrita eliminada exitosamente."));
                } else {
                    $this->messageManager->addErrorMessage(__("Error al eliminar tarjeta inscrita."));
                }
            } else {
                $this->messageManager->addErrorMessage(__("Tarjeta inscrita no encontrada"));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e, __("Error al eliminar tarjeta inscrita, contacta con soporte."));
        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }

    /**
     * @param $inscriptionId
     *
     * @throws \Exception
     *
     * @return OneclickInscriptionData
     */
    protected function getOneclickInscriptionData($inscriptionId)
    {
        $oneclickInscriptionDataModel = $this->oneclickInscriptionDataFactory->create();
        $oneclickInscriptionData = $oneclickInscriptionDataModel->load($inscriptionId, 'id');
        $tbkUser = $oneclickInscriptionData->getTbkUser();
        $username = $oneclickInscriptionData->getUsername();

        return [$username, $tbkUser, $oneclickInscriptionDataModel];
    }
}
