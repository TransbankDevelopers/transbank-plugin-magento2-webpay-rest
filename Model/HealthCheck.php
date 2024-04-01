<?php

namespace Transbank\Webpay\Model;

use Magento\Framework\Module\ModuleList;
use Magento\Framework\App\ProductMetadataInterface;
use Transbank\Webpay\Helper\ObjectManagerHelper;
use Transbank\Webpay\WebpayPlus;

class HealthCheck
{
    public $apiKey;
    public $commerceCode;
    public $environment;
    public $extensions;
    public $versionInfo;
    public $resume;
    public $fullResume;
    public $ecommerce;
    public $config;
    public $resExtensions;

    public function __construct($config)
    {
        $config['COMMERCE_CODE'] = WebpayPlus::DEFAULT_COMMERCE_CODE;
        $config['API_KEY'] = WebpayPlus::DEFAULT_API_KEY;
        $this->config = $config;
        $this->environment = $config['ENVIRONMENT'];
        $this->commerceCode = $config['COMMERCE_CODE'];
        $this->apiKey = $config['API_KEY'];
        $this->ecommerce = $config['ECOMMERCE'];
        // extensiones necesarias
        $this->extensions = [
            'dom',
        ];
    }

    // valida version de php
    private function getValidatephp()
    {
        if (version_compare(phpversion(), '7.2.1', '<=') and version_compare(phpversion(), '5.5.0', '>=')) {
            $this->versionInfo = [
                'status'  => 'OK',
                'version' => phpversion(),
            ];
        } else {
            $this->versionInfo = [
                'status'  => 'WARN: El plugin no ha sido testeado con esta version',
                'version' => phpversion(),
            ];
        }

        return $this->versionInfo;
    }

    // verifica si existe la extension y cual es la version de esta
    private function getCheckExtension($extension)
    {
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) or $version == null or $version === false or $version == ' ' or $version == '') {
                    $version = 'PHP Extension Compiled. ver:'.phpversion();
                }
            }
            $status = 'OK';
            $result = [
                'status'  => $status,
                'version' => $version,
            ];
        } else {
            $result = [
                'status'  => 'Error!',
                'version' => 'No Disponible',
            ];
        }

        return $result;
    }

    //obtiene ultimas versiones
    // permite un maximo de 60 consultas por hora
    private function getLastGitHubReleaseVersion($string)
    {
        $baseurl = 'https://api.github.com/repos/'.$string.'/releases/latest';
        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        $content = curl_exec($ch);
        curl_close($ch);
        $con = json_decode($content, true);
        $version = $con['tag_name'];

        return $version;
    }

    // funcion para obtener info de cada ecommerce, si el ecommerce es incorrecto o no esta seteado se escapa como respuesta "NO APLICA"
    private function getEcommerceInfo($ecommerce)
    {
        $productMetadata = ObjectManagerHelper::get(ProductMetadataInterface::class);
        $magentoVersion = $productMetadata->getVersion();
        $lastversion = $this->getLastGitHubReleaseVersion('Magento/Magento2');
        $plugininfo = ObjectManagerHelper::get(ModuleList::class)->getOne('Transbank_Webpay');
        $currentplugin = $plugininfo['setup_version'];
        $result = [
            'current_ecommerce_version' => $magentoVersion,
            'last_ecommerce_version'    => $lastversion,
            'current_plugin_version'    => $currentplugin,
        ];

        return $result;
    }

    // creacion de retornos
    // arma array que entrega informacion del ecommerce: nombre, version instalada, ultima version disponible
    private function getPluginInfo($ecommerce)
    {
        $data = $this->getEcommerceInfo($ecommerce);
        $result = [
            'ecommerce'              => $ecommerce,
            'ecommerce_version'      => $data['current_ecommerce_version'],
            'current_plugin_version' => $data['current_plugin_version'],
            'last_plugin_version'    => $this->getPluginLastVersion($ecommerce, $data['current_ecommerce_version']), // ultimo declarado
        ];

        return $result;
    }

    private function getPluginLastVersion($ecommerce, $currentversion)
    {
        return 'Indefinido';
    }

    // lista y valida extensiones/ modulos de php en servidor ademas mostrar version
    private function getExtensionsValidate()
    {
        foreach ($this->extensions as $value) {
            $this->resExtensions[$value] = $this->getCheckExtension($value);
        }

        return $this->resExtensions;
    }

    // crea resumen de informacion del servidor. NO incluye a PHP info
    private function getServerResume()
    {
        $this->resume = [
            'php_version'    => $this->getValidatephp(),
            'server_version' => ['server_software' => $_SERVER['SERVER_SOFTWARE']],
            'plugin_info'    => $this->getPluginInfo($this->ecommerce),
        ];

        return $this->resume;
    }

    // crea array con la informacion de comercio para posteriormente exportarla via json
    private function getCommerceInfo()
    {
        $result = [
            'environment'   => $this->environment,
            'commerce_code' => $this->commerceCode,
            'api_key'       => $this->apiKey,
        ];

        return ['data' => $result];
    }

    public function setCreateTransaction()
    {
        $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
        $amount = 990;
        $buyOrder = '_Healthcheck_';
        $sessionId = uniqid();
        $returnUrl = 'https://test_plugin_magento/return_url';
        $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);
        if ($result) {
            if (!empty($result['error']) && isset($result['error'])) {
                $status = 'Error';
            } else {
                $status = 'OK';
            }
        } else {
            if (array_key_exists('error', $result)) {
                $status = 'Error';
            }
        }
        $response = [
            'status'   => ['string' => $status],
            'response' => preg_replace('/<!--(.*)-->/Uis', '', $result),
        ];

        return $response;
    }

    //compila en solo un metodo toda la informacion obtenida, lista para imprimir
    private function getFullResume()
    {
        $this->fullResume = [
            'server_resume'          => $this->getServerResume(),
            'php_extensions_status'  => $this->getExtensionsValidate(),
            'commerce_info'          => $this->getCommerceInfo(),
        ];

        return $this->fullResume;
    }

    private function setpostinstall()
    {
        return false;
    }

    // imprime informacion de comercio y llaves
    public function printCommerceInfo()
    {
        return json_encode($this->getCommerceInfo());
    }

    // imprime en formato json la validacion de extensiones / modulos de php
    public function printExtensionStatus()
    {
        return json_encode($this->getExtensionsValidate());
    }

    // imprime en formato json informacion del servidor
    public function printServerResume()
    {
        return json_encode($this->getServerResume());
    }

    // imprime en formato json el resumen completo
    public function printFullResume()
    {
        return json_encode($this->getFullResume());
    }

    public function getCreateTransaction()
    {
        return json_encode($this->setCreateTransaction());
    }

    public function getpostinstallinfo()
    {
        return json_encode($this->setpostinstall());
    }
}
