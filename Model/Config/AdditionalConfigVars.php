<?php
namespace Transbank\Webpay\Model\Config;
use \Magento\Checkout\Model\ConfigProviderInterface;
use Transbank\Webpay\Helper\Inscriptions;

class AdditionalConfigVars implements ConfigProviderInterface
{
    protected $inscriptions;
    protected $configProvider;

    public function __construct(
        Inscriptions $inscriptions,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ){
        $this->inscriptions = $inscriptions;
        $this->configProvider = $configProvider;
    }

    public function getConfig()
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $additionalVariables['oneclick_max_amount'] = $config['TRANSACTION_MAX_AMOUNT'];
        $additionalVariables['oneclick_inscriptions'] = $this->inscriptions->getInscriptions();
        return $additionalVariables;
    }
}

?>
