<?php

namespace Transbank\Webpay\Observer;

use GuzzleHttp\Client;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class SaveConfigObserver implements ObserverInterface
{

    protected $_logger;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        RequestInterface $request,
        WriterInterface $configWriter)
    {
        $this->request = $request;
        $this->_logger = $logger;
        $this->configWriter = $configWriter;
    }

    
    public function execute(EventObserver $observer)
    {
        try {

            $params = $this->request->getParam('groups');
            $orderStatus = $params['transbank_webpay']['groups']['general_parameters']['fields']['payment_successful_status']['value'];

            $this->sendMetrics($params);

            if ($orderStatus !== 'processing') {
                $value = 'default';
                $this->configWriter->save('payment/transbank_webpay/general_parameters/invoice_settings', $value);
            }

            $oneclickOrderStatus = isset($params['transbank_oneclick']['groups']['general_parameters']['fields']['payment_successful_status']['value']) ? : 0;

            if ($oneclickOrderStatus !== 'processing') {
                $value = 'default';
                $this->configWriter->save('payment/transbank_oneclick/general_parameters/invoice_settings', $value);
            }

            return $this;
        } catch (\ErrorException $e) {
            $this->_logger->critical($e);
        }
    }

    public function sendMetrics($params) {
        try {
            $this->_logger->info('Sending Metric');

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
            $moduleInfo =  $objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Transbank_Webpay'); // SR_Learning is module name
            $pluginVersion = $moduleInfo['setup_version'];

            $webpayEnviroment = $params['transbank_webpay']['groups']['security']['fields']['environment']['value'];
            $webpayCommerceCode = $params['transbank_webpay']['groups']['security']['fields']['commerce_code']['value'];

            $oneclickEnviroment = $params['transbank_oneclick']['groups']['security']['fields']['environment']['value'];
            $oneclickCommerceCode = $params['transbank_oneclick']['groups']['security']['fields']['commerce_code']['value'];

            $webpayPayload = [    
                'commerceCode' => 1,
                'plugin' => 'magento',
                'environment' => $webpayEnviroment,
                'product' => 'webpay',
                'pluginVersion' => $pluginVersion,
                'commerceCode' => $webpayCommerceCode,
                'phpVersion' => phpversion(),
                'metadata' => json_encode(['magentoVersion' => $productMetadata->getVersion()]),
            ];

            $oneclickPayload = [    
                'commerceCode' => 1,
                'plugin' => 'magento',
                'environment' => $oneclickEnviroment,
                'product' => 'oneclick',
                'pluginVersion' => $pluginVersion,
                'commerceCode' => $oneclickCommerceCode,
                'phpVersion' => phpversion(),
                'metadata' => json_encode(['magentoVersion' => $productMetadata->getVersion()]),
            ];

            $client = new Client();

            $client->request('POST', 'https://tbk-app-y8unz.ondigitalocean.app/records/newRecord', ['form_params' => $webpayPayload]);
            
            $client->request('POST', 'https://tbk-app-y8unz.ondigitalocean.app/records/newRecord', ['form_params' => $oneclickPayload]);

            $this->_logger->info('Saved');
        } catch (\ErrorException $e) {
            $this->_logger->critical($e);
        }

    }
}
