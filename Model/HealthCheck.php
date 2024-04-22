<?php

namespace Transbank\Webpay\Model;

use Magento\Framework\Module\ModuleList;
use Magento\Framework\App\ProductMetadataInterface;
use Transbank\Webpay\Helper\ObjectManagerHelper;
use Transbank\Webpay\WebpayPlus;

class HealthCheck
{
    const MAGENTO_REPOSITORY = 'Magento/Magento2';
    const PLUGIN_REPOSITORY = 'TransbankDevelopers/transbank-plugin-magento2-webpay-rest';

    /**
     * Transbank API Key.
     * @var string
     */
    public $apiKey;

    /**
     * Transbank commerce code.
     * @var string
     */
    public $commerceCode;

    /**
     * The environment.
     * @var string
     */
    public $environment;

    /**
     * List of required PHP extension.
     * @var array
     */
    public $extensions;

    /**
     * PHP version support status.
     * @var array
     */
    public $versionInfo;

    /**
     * Summary of server information.
     * @var array
     */
    public $resume;

    /**
     * Summary for the all plugin information.
     * @var array
     */
    public $fullResume;

    /**
     * The name of the ecommerce platform.
     * @var string
     */
    public $ecommerce;

    /**
     * Configuration data.
     * @var array
     */
    public $config;

    /**
     * Results of extension validation.
     * @var array
     */
    public $resExtensions;

    /**
     * Initializes the HealthCheck instance with configuration data.
     *
     * @param array $config The configuration data.
     */
    public function __construct($config)
    {
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

    /**
     * Validate PHP version.
     *
     * Checks if the PHP version is supported.
     *
     * @return array Information about PHP version validation.
     */
    private function getValidatePHP()
    {
        if (version_compare(phpversion(), '7.4.33', '<=') && version_compare(phpversion(), '5.5.0', '>=')) {
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

    /**
     * Check PHP extension.
     *
     * Checks if a PHP extension is loaded and retrieves its version.
     *
     * @param string $extension The name of the PHP extension to check.
     * @return array Information about the PHP extension.
     */
    private function getCheckExtension($extension)
    {
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) || $version == null || $version === false || $version == ' ' || $version == '') {
                    $version = 'PHP Extension Compiled. ver:' . phpversion();
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

    /**
     * Get last release version from GitHub.
     *
     * Retrieves the latest release version of a repository from GitHub.
     *
     * @param string $string The GitHub repository name.
     * @return string The latest release version.
     */
    private function getLastGitHubReleaseVersion($string)
    {
        $baseurl = 'https://api.github.com/repos/' . $string . '/releases/latest';
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

    /**
     * Get last Magento release version.
     *
     * Retrieves the latest release version of Magento from GitHub.
     *
     * @return string The latest Magento release version.
     */
    private function getLastMagentoReleaseVersion()
    {
        return $this->getLastGitHubReleaseVersion(self::MAGENTO_REPOSITORY);
    }

    /**
     * Get last plugin release version.
     *
     * Retrieves the latest release version of the plugin from GitHub.
     *
     * @return string The latest plugin release version.
     */
    private function getLastPluginReleaseVersion()
    {
        return $this->getLastGitHubReleaseVersion(self::PLUGIN_REPOSITORY);
    }

    /**
     * Get ecommerce info.
     *
     * Retrieves information about the ecommerce platform and the plugin.
     *
     * @param string $ecommerce The name of the ecommerce platform.
     * @return array Information about the ecommerce and plugin versions.
     */
    private function getEcommerceInfo()
    {
        $productMetadata = ObjectManagerHelper::get(ProductMetadataInterface::class);
        $magentoVersion = $productMetadata->getVersion();
        $lastversion = $this->getLastMagentoReleaseVersion();
        $plugininfo = ObjectManagerHelper::get(ModuleList::class)->getOne('Transbank_Webpay');
        $currentplugin = $plugininfo['setup_version'];
        $result = [
            'current_ecommerce_version' => $magentoVersion,
            'last_ecommerce_version'    => $lastversion,
            'current_plugin_version'    => $currentplugin,
        ];

        return $result;
    }

    /**
     * Get plugin info.
     *
     * Retrieves information about the plugin version.
     *
     * @param string $ecommerce The name of the ecommerce platform.
     * @return array Information about the plugin version.
     */
    private function getPluginInfo()
    {
        $data = $this->getEcommerceInfo();
        $result = [
            'ecommerce'              => $this->ecommerce,
            'ecommerce_version'      => $data['current_ecommerce_version'],
            'last_ecommerce_version' => $data['last_ecommerce_version'],
            'current_plugin_version' => $data['current_plugin_version'],
            'last_plugin_version'    => $this->getLastPluginReleaseVersion(),
        ];

        return $result;
    }

    /**
     * Validate PHP extensions.
     *
     * Checks if required PHP extensions are loaded and retrieves their versions.
     *
     * @return array Information about PHP extensions.
     */
    private function getExtensionsValidate()
    {
        foreach ($this->extensions as $value) {
            $this->resExtensions[$value] = $this->getCheckExtension($value);
        }

        return $this->resExtensions;
    }

    /**
     * Get server resume.
     *
     * Retrieves a summary of server information.
     *
     * @return array Summary of server information.
     */
    private function getServerResume()
    {
        $this->resume = [
            'php_version'    => $this->getValidatePHP(),
            'server_version' => ['server_software' => $_SERVER['SERVER_SOFTWARE']],
            'plugin_info'    => $this->getPluginInfo(),
        ];

        return $this->resume;
    }

    /**
     * Get commerce info.
     *
     * Retrieves information about the commerce configuration.
     *
     * @return array Information about commerce configuration.
     */
    private function getCommerceInfo()
    {
        $result = [
            'environment'   => $this->environment,
            'commerce_code' => $this->commerceCode,
            'api_key'       => $this->apiKey,
        ];

        return ['data' => $result];
    }

    /**
     * Create transaction.
     *
     * Creates a transaction using Transbank SDK.
     *
     * @return array Information about the transaction.
     */
    public function createTestTransaction()
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

    /**
     * Get full resume.
     *
     * Retrieves a full summary of server information.
     *
     * @return array Full summary of server information.
     */
    public function getFullResume()
    {
        $this->fullResume = [
            'server_resume'          => $this->getServerResume(),
            'php_extensions_status'  => $this->getExtensionsValidate(),
            'commerce_info'          => $this->getCommerceInfo(),
        ];

        return $this->fullResume;
    }
}
