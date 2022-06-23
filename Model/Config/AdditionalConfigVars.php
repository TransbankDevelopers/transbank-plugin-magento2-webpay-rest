<?php
namespace Transbank\Webpay\Model\Config;
use \Magento\Checkout\Model\ConfigProviderInterface;
use Transbank\Webpay\Helper\Inscriptions;

class AdditionalConfigVars implements ConfigProviderInterface
{
    protected $getInscriptions;
    public function __construct(Inscriptions $getInscriptions){
        $this->_getInscriptions = $getInscriptions;
    }

    public function getConfig()
    {
        $inscriptions = $this->_getInscriptions->getInscriptions();

        $additionalVariables['oneclick_inscriptions'] = $inscriptions;
        return $additionalVariables;
    }
}

?>