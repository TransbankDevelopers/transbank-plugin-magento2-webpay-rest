<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use \Psr\Log\LoggerInterface;

class SaveConfigObserver implements ObserverInterface
{

    const TRANSBANK_WEBPAY_PAYMENT_SUCCESSFUL_STATUS = 'payment/transbank_webpay/general_parameters/payment_successful_status';
    const TRANSBANK_ONECLICK_PAYMENT_SUCCESSFUL_STATUS = 'payment/transbank_oneclick/general_parameters/payment_successful_status';
    const TRANSBANK_ONECLICK_INVOICE_SETTINGS = 'payment/transbank_oneclick/general_parameters/invoice_settings';
    const TRANSBANK_WEBPAY_INVOICE_SETTINGS = 'payment/transbank_webpay/general_parameters/invoice_settings';

    protected $request;
    protected $configWriter;

    protected LoggerInterface $_logger;
    protected ScopeConfigInterface $scopeConfig;
    protected StoreManagerInterface $storeManager;

    public function __construct (
        LoggerInterface $logger,
        RequestInterface $request,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager)
    {
        $this->request = $request;
        $this->_logger = $logger;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }


    public function execute(EventObserver $observer)
    {
        $websiteId = $this->getWebsiteId();

        $params = $this->request->getParam('groups');

        if( isset($params['transbank_webpay']['groups']['general_parameters']['fields']['payment_successful_status']['value']) ){
            $orderStatus = $params['transbank_webpay']['groups']['general_parameters']['fields']['payment_successful_status']['value'];
        } elseif (empty($this->scopeConfig->getValue(self::TRANSBANK_WEBPAY_PAYMENT_SUCCESSFUL_STATUS,ScopeInterface::SCOPE_WEBSITE, $websiteId ))) {
            $orderStatus = $this->scopeConfig->getValue(self::TRANSBANK_WEBPAY_PAYMENT_SUCCESSFUL_STATUS,ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        } else {
            $orderStatus = $this->scopeConfig->getValue(self::TRANSBANK_WEBPAY_PAYMENT_SUCCESSFUL_STATUS,ScopeInterface::SCOPE_WEBSITE, $websiteId );
        }

        if ($orderStatus !== 'processing') {
            $value = 'default';
            $this->configWriter->save(self::TRANSBANK_WEBPAY_INVOICE_SETTINGS, $value);
        }

        if(isset($params['transbank_oneclick']['groups']['general_parameters']['fields']['payment_successful_status']['value']) ) {
            $oneclickOrderStatus = $params['transbank_oneclick']['groups']['general_parameters']['fields']['payment_successful_status']['value'];
        }elseif (empty($this->scopeConfig->getValue(self::TRANSBANK_ONECLICK_PAYMENT_SUCCESSFUL_STATUS,ScopeInterface::SCOPE_WEBSITE, $websiteId))) {
            $oneclickOrderStatus = $this->scopeConfig->getValue(self::TRANSBANK_ONECLICK_PAYMENT_SUCCESSFUL_STATUS,ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        }else {
            $oneclickOrderStatus = $this->scopeConfig->getValue(self::TRANSBANK_ONECLICK_PAYMENT_SUCCESSFUL_STATUS,ScopeInterface::SCOPE_WEBSITE, $websiteId);
        }

        if ($oneclickOrderStatus !== 'processing') {
            $value = 'default';
            $this->configWriter->save(self::TRANSBANK_ONECLICK_INVOICE_SETTINGS, $value);
        }

        return $this;
    }

    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getWebsiteId(): int
    {
        $storeId = (int)$this->request->getParam('store', 0);
        $store = $this->storeManager->getStore($storeId);
        $websiteId = $store->getWebsiteId();
        return $websiteId;
    }
}

