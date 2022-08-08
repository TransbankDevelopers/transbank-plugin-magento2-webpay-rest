<?php

namespace Transbank\Webpay\Observer;

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
        $this->_logger->info('Invoice Observer');

        $params = $this->request->getParam('groups');
        $orderStatus = $params['transbank_webpay']['groups']['general_parameters']['fields']['payment_successful_status']['value'];

        if ($orderStatus !== 'processing') {
            $value = 'default';
            $this->configWriter->save('payment/transbank_webpay/general_parameters/invoice_settings', $value);
        }

        $oneclickOrderStatus = $params['transbank_oneclick']['groups']['general_parameters']['fields']['payment_successful_status']['value'];

        if ($oneclickOrderStatus !== 'processing') {
            $value = 'default';
            $this->configWriter->save('payment/transbank_oneclick/general_parameters/invoice_settings', $value);
        }

        return $this;
    }
}
