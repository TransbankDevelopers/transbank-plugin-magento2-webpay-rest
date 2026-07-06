<?php
namespace Transbank\Webpay\Model\Config;
use \Magento\Checkout\Model\ConfigProviderInterface;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class AdditionalConfigVars implements ConfigProviderInterface
{
    protected $oneclickInscriptionService;
    protected $configProvider;

    public function __construct(
        OneclickInscriptionService $oneclickInscriptionService,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider
    ){
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->configProvider = $configProvider;
    }

    public function getConfig()
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $additionalVariables['oneclick_max_amount'] = $config['TRANSACTION_MAX_AMOUNT'];
        $additionalVariables['oneclick_inscriptions'] = $this->oneclickInscriptionService->getInscriptionsForCurrentCustomer();
        return $additionalVariables;
    }
}

?>
