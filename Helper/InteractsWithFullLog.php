<?php
namespace Transbank\Webpay\Helper;
use Magento\Framework\App\Helper\AbstractHelper;

class InteractsWithFullLog extends AbstractHelper {

    protected $log;
    public function __construct()
    {
        $this->log = new PluginLogger();
    }

    public function logWebpayPlusIniciando(){
        $this->log->logInfo('B.1. Iniciando medio de pago Webpay Plus');
    }

    public function logWebpayPlusAntesCrearTx($amount, $sessionId, $buyOrder, $returnUrl){
        $this->log->logInfo('B.2. Preparando datos antes de crear la transacción en Transbank');
        $this->log->logInfo('amount: '.$amount.', sessionId: '.$sessionId.', buyOrder: '.$buyOrder.', returnUrl: '.$returnUrl);
    }

    public function logWebpayPlusDespuesCrearTx($result){
        $this->log->logInfo('B.3. Transacción creada en Transbank');
        $this->log->logInfo(json_encode($result));
    }

    public function logWebpayPlusDespuesCrearTxError($result){
        $this->log->logError('B.3. Transacción creada con error en Transbank');
        $this->log->logError(json_encode($result));
    }

}
