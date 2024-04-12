<?php

namespace Transbank\Webpay\Block\System\Config;

use Transbank\Webpay\Model\HealthCheck;
use Transbank\Webpay\Helper\PluginLogger;

class TbkButton extends \Magento\Config\Block\System\Config\Form\Field
{
    public $tbk_data;
    /**
     * @var string
     */
    protected $_template = 'system/config/button.phtml';

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ) {
        parent::__construct($context);

        $config = $configProvider->getPluginConfig();

        $healthcheck = new HealthCheck($config);
        $datos_hc = $healthcheck->getFullResume();

        $logger = new PluginLogger();
        $logInfo = $logger->getInfo();
        $logDetail = [];

        if (isset($logInfo['last'])) {
            $logDetail = $logger->getLogDetail($logInfo['last']);
        }

        $this->tbk_data = [
            'url_request'             => $context->getUrlBuilder()->getUrl('admin_webpay/Request/index'),
            'php_status'              => $datos_hc['server_resume']['php_version']['status'],
            'php_version'             => $datos_hc['server_resume']['php_version']['version'],
            'server_version'          => $datos_hc['server_resume']['server_version']['server_software'],
            'ecommerce'               => $datos_hc['server_resume']['plugin_info']['ecommerce'],
            'ecommerce_version'       => $datos_hc['server_resume']['plugin_info']['ecommerce_version'],
            'last_ecommerce_version'  => $datos_hc['server_resume']['plugin_info']['last_ecommerce_version'],
            'current_plugin_version'  => $datos_hc['server_resume']['plugin_info']['current_plugin_version'],
            'last_plugin_version'     => $datos_hc['server_resume']['plugin_info']['last_plugin_version'],
            'dom_status'              => $datos_hc['php_extensions_status']['dom']['status'],
            'dom_version'             => $datos_hc['php_extensions_status']['dom']['version'],
            'logs'                    => isset($logDetail['content']) ? $logDetail['content'] : '',
            'log_file'                => isset($logInfo['last']) ? $logInfo['last'] : '-',
            'log_weight'              => isset($logDetail['size']) ? $logDetail['size'] : '-',
            'log_regs_lines'          => isset($logDetail['lines']) ? $logDetail['lines'] : '-',
            'log_dir'                 => $logInfo['dir'],
            'logs_count'              => $logInfo['length'],
            'logs_list'               => isset($logInfo['logs']) ? $logInfo['logs'] : ['no hay archivos de registro'],
        ];
    }

    /**
     * Return element html.
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        return $this->_toHtml();
    }
}
