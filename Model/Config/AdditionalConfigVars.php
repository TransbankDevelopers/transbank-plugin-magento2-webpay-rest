<?php
namespace Transbank\Webpay\Model\Config;
use \Magento\Checkout\Model\ConfigProviderInterface;
use Transbank\Webpay\Helper\Inscriptions;

class AdditionalConfigVars implements ConfigProviderInterface
{
    protected $getInscriptions;
    protected $configProvider;
    protected $_getInscriptions;

    public function __construct(
        Inscriptions $getInscriptions,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ){
        $this->_getInscriptions = $getInscriptions;
        $this->configProvider = $configProvider;
    }

    public function getConfig()
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $inscriptions = $this->_getInscriptions->getInscriptions();

        $additionalVariables['oneclick_max_amount'] = $config['TRANSACTION_MAX_AMOUNT'];
        $additionalVariables['oneclick_inscriptions'] = $inscriptions;
        return $additionalVariables;
    }
}

?>
