<?php
namespace Transbank\Webpay\Model\Config;
use \Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;

class AdditionalConfigVars implements ConfigProviderInterface
{
    protected $oneclickInscriptionService;
    protected $configProvider;
    protected $customerSession;

    public function __construct(
        OneclickInscriptionService $oneclickInscriptionService,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        CustomerSession $customerSession
    ){
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->configProvider = $configProvider;
        $this->customerSession = $customerSession;
    }

    public function getConfig()
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $additionalVariables['oneclick_max_amount'] = $config['TRANSACTION_MAX_AMOUNT'];
        $additionalVariables['oneclick_inscriptions'] = $this->oneclickInscriptionService->getActiveInscriptionsByCustomerId($this->customerSession->getCustomer()->getId());
        return $additionalVariables;
    }
}

?>
