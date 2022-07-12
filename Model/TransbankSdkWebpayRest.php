<?php

namespace Transbank\Webpay\Model;

use Exception;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionFinishException;

use Transbank\Webpay\Oneclick\MallTransaction;

/**
 * Class TransbankSdkWebpayRest.
 */
class TransbankSdkWebpayRest
{
    /**
     * @var Options
     */
    public $options;
    /**
     * @var LogHandler
     */
    protected $log;

    /**
     * @var WebpayPlus\Transaction
     */
    public $transaction;

    /**
     * @var Oneclick\MallInscription
     */
    public $mallInscription;

    /**
     * @var Oneclick\MallTransaction
     */
    public $mallTransaction;

    /**
     * TransbankSdkWebpayRest constructor.
     *
     * @param $config
     * @param $product
     */
    public function __construct($config)
    {
        $this->log = new LogHandler();
        if (isset($config)) {
            $environment = isset($config['ENVIRONMENT']) ? $config['ENVIRONMENT'] : 'TEST';
            $this->transaction = new WebpayPlus\Transaction();
            $this->options = ($environment != 'TEST') ? $this->transaction->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']) : $this->transaction->configureForIntegration(WebpayPlus::DEFAULT_COMMERCE_CODE, WebpayPlus::DEFAULT_API_KEY);
        
            $this->inscription = new Oneclick\MallInscription();

            $this->mallTransaction = new Oneclick\MallTransaction();

        }
    }

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     *
     * @throws Exception
     *
     * @return array
     */
    public function createTransaction($amount, $sessionId, $buyOrder, $returnUrl)
    {
        $result = [];

        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('initTransaction - amount: '.$amount.', sessionId: '.$sessionId.
                ', buyOrder: '.$buyOrder.', txDate: '.$txDate.', txTime: '.$txTime);

            $initResult = $this->transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

            $this->log->logInfo('createTransaction - initResult: '.json_encode($initResult));
            if (isset($initResult) && isset($initResult->url) && isset($initResult->token)) {
                $result = [
                    'url'      => $initResult->url,
                    'token_ws' => $initResult->token,
                ];
            } else {
                throw new Exception('No se ha creado la transacción para, amount: '.$amount.', sessionId: '.$sessionId.', buyOrder: '.$buyOrder);
            }
        } catch (TransactionCreateException $e) {
            $result = [
                'error'  => 'Error al crear la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $tokenWs
     *
     * @throws Exception
     *
     * @return array|WebpayPlus\Transaction
     */
    public function commitTransaction($tokenWs)
    {
        try {
            $this->log->logInfo('getTransactionResult - tokenWs: '.$tokenWs);
            if ($tokenWs == null) {
                throw new Exception('El token webpay es requerido');
            }

            return $this->transaction->commit($tokenWs);
        } catch (TransactionCommitException $e) {
            $result = [
                'error'  => 'Error al confirmar la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $username
     * @param $email
     * @param $responseUrl
     *
     * @throws Exception
     *
     * @return array
     */
    public function createInscription($username, $email, $responseUrl)
    {
        $result = [];

        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('initInscription - Username: '.$username.', email: '.$email.
                ', responseUrl: '.$responseUrl);

            $initResult = $this->inscription->start($username, $email, $responseUrl);

            $this->log->logInfo('createInscription - initResult: '.json_encode($initResult));
            if (isset($initResult) && isset($initResult->token) && isset($initResult->urlWebpay)) {
                $result = [
                    'token'      => $initResult->token,
                    'urlWebpay' => $initResult->urlWebpay,
                ];
            } else {
                throw new Exception('No se ha creado la inscripción para, username: '.$username.', email: '.$email.', responseUrl: '.$responseUrl);
            }
        } catch (InscriptionStartException $e) {
            $result = [
                'error'  => 'Error al crear la inscripción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $tbkToken
     *
     * @throws Exception
     *
     * @return array
     */
    public function finishInscription($tbkToken)
    {
        try {
            $this->log->logInfo('getInscriptonResult - tokenWs: '.$tbkToken);
            if ($tbkToken == null) {
                throw new Exception('El token tokenWs es requerido');
            }

            return $this->inscription->finish($tbkToken);
        } catch (InscriptionFinishException $e) {
            $result = [
                'error'  => 'Error al confirmar la inscripción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $username
     * @param $tbkUser
     * @param $total
     *
     * @throws Exception
     *
     * @return array
     */
    public function authorizeTransaction($customerId, $username, $tbkUser, $details)
    {
        try {
            $this->log->logInfo('authorizeTransaction - username: '.$username);
            if ($username == null || $tbkUser == null) {
                throw new Exception('El token tbkUser y el username son requerido');
            }

            return $this->mallTransaction->authorize($username, $tbkUser, $customerId, $details);

        } catch (InscriptionFinishException $e) {
            $result = [
                'error'  => 'Error al autorizar la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }
}
