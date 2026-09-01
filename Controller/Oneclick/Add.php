<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\UrlInterface;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class Add extends Action implements HttpPostActionInterface
{
    private const GENERIC_ERROR = 'No fue posible iniciar la inscripción de la tarjeta.';

    private $customerSession;
    private $urlBuilder;
    private $inscriptionService;
    private $logger;
    private $rawResultFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        UrlInterface $urlBuilder,
        OneclickInscriptionService $inscriptionService,
        PluginLogger $logger,
        RawFactory $rawResultFactory
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
        $this->inscriptionService = $inscriptionService;
        $this->logger = $logger;
        $this->rawResultFactory = $rawResultFactory;
    }

    public function execute()
    {
        try {
            return $this->handleRequest();
        } catch (\Throwable $e) {
            $this->logger->logError('Error al iniciar inscripción privada.', [
                'exception' => get_class($e),
                'message' => 'Private inscription start failed.',
                'customer_id' => $this->customerSession->getCustomerId(),
            ]);
            $this->messageManager->addErrorMessage(__(self::GENERIC_ERROR));

            return $this->resultRedirectFactory->create()->setPath('customer/oneclick/index');
        }
    }

    private function handleRequest()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->urlBuilder->getUrl('customer/oneclick/index'));

            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $customer = $this->customerSession->getCustomer();
        $returnUrl = $this->urlBuilder->getUrl(
            'customer/oneclick/commit',
            ['_secure' => true, '_scope_to_url' => true]
        );

        $inscription = $this->inscriptionService->startPrivateInscription(
            (int) $customer->getId(),
            (string) $customer->getEmail(),
            $returnUrl
        );

        return $this->createWebpayForm($inscription['webpayUrl'], $inscription['token']);
    }

    private function createWebpayForm(string $webpayUrl, string $token)
    {
        $escape = static function (string $value): string {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $result = $this->rawResultFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setContents(
            '<!doctype html><html><head><meta charset="UTF-8"><title>Oneclick</title></head><body>' .
                '<form method="post" name="webpayForm" action="' . $escape($webpayUrl) . '">' .
                '<input type="hidden" name="TBK_TOKEN" value="' . $escape($token) . '">' .
                '</form>' .
                '<script>document.webpayForm.submit();</script>' .
                '</body></html>'
        );

        return $result;
    }
}
