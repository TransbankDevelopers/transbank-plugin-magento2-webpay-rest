<?php

namespace Transbank\Webpay\Controller\Adminhtml\Request;

use Transbank\Webpay\Model\HealthCheck;
use Transbank\Webpay\Exceptions\TransbankCreateException;

class Index extends \Magento\Backend\App\Action
{
    protected $configProvider;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ) {
        parent::__construct($context);
        $this->configProvider = $configProvider;
    }

    /**
     * @Override
     */
    public function execute()
    {
        if ($_POST['type'] == 'checkInit') {
            try {
                $config = $this->configProvider->getPluginConfig();
                $healthcheck = new HealthCheck($config);
                $response = $healthcheck->createTestTransaction();

                echo json_encode(['success' => true, 'msg' => $response]);
            } catch (TransbankCreateException $e) {
                echo json_encode(['success' => false, 'msg' => 'No se pudo crear la transacción de pruebas.']);
            }
        }
    }
}
