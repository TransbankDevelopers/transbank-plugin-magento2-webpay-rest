<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\UrlInterface;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Infrastructure\Lock\MySqlNamedLock;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Exceptions\TransbankException;

class Add extends Action implements HttpPostActionInterface
{
    private const GENERIC_ERROR = 'No fue posible iniciar la inscripción de la tarjeta.';

    private $customerSession;
    private $configProvider;
    private $urlBuilder;
    private $inscriptionFactory;
    private $inscriptionService;
    private $logger;
    private $lock;
    private $rawResultFactory;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        ConfigProvider $configProvider,
        UrlInterface $urlBuilder,
        OneclickInscriptionDataFactory $inscriptionFactory,
        OneclickInscriptionService $inscriptionService,
        PluginLogger $logger,
        MySqlNamedLock $lock,
        RawFactory $rawResultFactory
    ) {
        parent::__construct($context);
        $this->customerSession = $customerSession;
        $this->configProvider = $configProvider;
        $this->urlBuilder = $urlBuilder;
        $this->inscriptionFactory = $inscriptionFactory;
        $this->inscriptionService = $inscriptionService;
        $this->logger = $logger;
        $this->lock = $lock;
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
        $customerId = (int) $customer->getId();
        $email = (string) $customer->getEmail();
        $config = $this->configProvider->getPluginConfigOneclick();
        $returnUrl = $this->urlBuilder->getUrl(
            'customer/oneclick/commit',
            ['_secure' => true, '_scope_to_url' => true]
        );
        $lockKey = 'transbank_private_oneclick_add_' . $customerId;

        if (!$this->lock->acquire($lockKey)) {
            throw new TransbankException('No se pudo serializar la inscripción.');
        }

        try {
            $username = $this->inscriptionService->generateInscriptionUsername($customerId);
            $response = (new TransbankSdkWebpayRest($config))->createInscription($username, $email, $returnUrl);
            $token = $response['token'] ?? null;
            $webpayUrl = $response['urlWebpay'] ?? null;

            if (!$this->isValidResponseValue($token) || !$this->isValidHttpsUrl($webpayUrl)) {
                throw new TransbankException('Respuesta inválida al iniciar inscripción.');
            }

            $inscription = $this->inscriptionFactory->create();
            $inscription->setData([
                'status' => OneclickInscriptionData::PAYMENT_STATUS_WATING,
                'token' => $token,
                'username' => $username,
                'email' => $email,
                'user_id' => $customerId,
                'environment' => $config['ENVIRONMENT'] ?? null,
                'commerce_code' => $config['COMMERCE_CODE'] ?? null,
                'metadata' => json_encode(['source' => 'private'], JSON_THROW_ON_ERROR),
            ]);
            $this->inscriptionService->save($inscription);

            return $this->createWebpayForm($webpayUrl, $token);
        } finally {
            $this->lock->release($lockKey);
        }
    }

    private function isValidResponseValue($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function isValidHttpsUrl($url): bool
    {
        return $this->isValidResponseValue($url) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
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
