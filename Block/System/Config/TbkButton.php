<?php

namespace Transbank\Webpay\Block\System\Config;

use Transbank\Webpay\Model\HealthCheck;
use Transbank\Webpay\Model\LogHandler;

class TbkButton extends \Magento\Config\Block\System\Config\Form\Field
{
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
        $datos_hc = json_decode($healthcheck->printFullResume());

        $log = new LogHandler();
        $resume = $log->getResume();

        $this->tbk_data = [
            'url_request'             => $context->getUrlBuilder()->getUrl('admin_webpay/Request/index'),
            'url_call_log_handler'    => $context->getUrlBuilder()->getUrl('admin_webpay/CallLogHandler/index'),
            'url_create_pdf_report'   => $context->getUrlBuilder()->getUrl('admin_webpay/CreatePdf/index').'?document=report',
            'url_create_pdf_php_info' => $context->getUrlBuilder()->getUrl('admin_webpay/CreatePdf/index').'?document=php_info',
            'init_status'             => null, //$datos_hc->validate_init_transaction->status->string,
            'init_error_error'        => null, // (isset($datos_hc->validate_init_transaction->response->error)) ? $datos_hc->validate_init_transaction->response->error : NULL,
            'init_error_detail'       => null, // (isset($datos_hc->validate_init_transaction->response->detail)) ? $datos_hc->validate_init_transaction->response->detail : NULL,
            'init_success_url'        => null, // (isset($datos_hc->validate_init_transaction->response->url)) ? $datos_hc->validate_init_transaction->response->url : NULL,
            'init_success_token'      => null, //  (isset($datos_hc->validate_init_transaction->response->token_ws)) ? $datos_hc->validate_init_transaction->response->token_ws : NULL,
            'php_status'              => $datos_hc->server_resume->php_version->status,
            'php_version'             => $datos_hc->server_resume->php_version->version,
            'server_version'          => $datos_hc->server_resume->server_version->server_software,
            'ecommerce'               => $datos_hc->server_resume->plugin_info->ecommerce,
            'ecommerce_version'       => $datos_hc->server_resume->plugin_info->ecommerce_version,
            'current_plugin_version'  => $datos_hc->server_resume->plugin_info->current_plugin_version,
            'last_plugin_version'     => $datos_hc->server_resume->plugin_info->last_plugin_version,
            'dom_status'              => $datos_hc->php_extensions_status->dom->status,
            'dom_version'             => $datos_hc->php_extensions_status->dom->version,
            'php_info'                => $datos_hc->php_info->string->content,
            'lockfile'                => isset($resume['lock_file']['status']) ? $resume['lock_file']['status'] : null,
            'logs'                    => isset($resume['last_log']['log_content']) ? $resume['last_log']['log_content'] : null,
            'log_file'                => isset($resume['last_log']['log_file']) ? $resume['last_log']['log_file'] : null,
            'log_weight'              => isset($resume['last_log']['log_weight']) ? $resume['last_log']['log_weight'] : null,
            'log_regs_lines'          => isset($resume['last_log']['log_regs_lines']) ? $resume['last_log']['log_regs_lines'] : null,
            'log_days'                => $resume['validate_lock_file']['max_logs_days'],
            'log_size'                => $resume['validate_lock_file']['max_log_weight'],
            'log_dir'                 => $resume['log_dir'],
            'logs_count'              => $resume['logs_count']['log_count'],
            'logs_list'               => isset($resume['logs_list']) ? $resume['logs_list'] : ['no hay archivos de registro'],
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
