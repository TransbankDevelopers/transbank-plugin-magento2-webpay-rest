<?php

namespace Transbank\Webpay\Model;

use Exception;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionRefundException;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionFinishException;
use Transbank\Webpay\Oneclick\Exceptions\MallRefundTransactiionException;

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
            $this->mallInscription = new Oneclick\MallInscription();
            $this->mallTransaction = new Oneclick\MallTransaction();

            $this->log->logInfo('Environment: '.json_encode($environment));

            if ($environment != 'TEST') {
                $this->options = $this->transaction->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
                $this->options = $this->mallInscription->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
                $this->options = $this->mallTransaction->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
            } else {
                $this->options = $this->transaction->configureForIntegration(WebpayPlus::DEFAULT_COMMERCE_CODE, WebpayPlus::DEFAULT_API_KEY);
                $this->options = $this->mallInscription->configureForIntegration(Oneclick::DEFAULT_COMMERCE_CODE, Oneclick::DEFAULT_API_KEY);
                $this->options = $this->mallTransaction->configureForIntegration(Oneclick::DEFAULT_COMMERCE_CODE, Oneclick::DEFAULT_API_KEY);
            }

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
            $this->log->logInfo('createTransaction - amount: '.$amount.', sessionId: '.$sessionId.
                ', buyOrder: '.$buyOrder.', txDate: '.$txDate.', txTime: '.$txTime);

            $createResult = $this->transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

            $this->log->logInfo('createTransaction - createResult: '.json_encode($createResult));
            if (isset($createResult) && isset($createResult->url) && isset($createResult->token)) {
                $result = [
                    'url'      => $createResult->url,
                    'token_ws' => $createResult->token,
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
            if ($tokenWs == null) {
                throw new Exception('El token webpay es requerido');
            }

            $transaction = $this->transaction->commit($tokenWs);

            $this->log->logInfo('commitTransaction: '.json_encode($transaction));
            return $transaction;
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

            $initResult = $this->mallInscription->start($username, $email, $responseUrl);

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

            $inscription = $this->mallInscription->finish($tbkToken);
            $this->log->logInfo('finishInscription: '.json_encode($inscription));

            return $inscription;
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
    public function authorizeTransaction($username, $tbkUser, $buyOrder, $details)
    {
        try {
            if ($username == null || $tbkUser == null) {
                throw new Exception('El token tbkUser y el username son requerido');
            }

            $transaction = $this->mallTransaction->authorize($username, $tbkUser, $buyOrder, $details);
            $this->log->logInfo('authorizeTransaction: '.json_encode($transaction));

            return $transaction;

        } catch (InscriptionFinishException $e) {
            $result = [
                'error'  => 'Error al autorizar la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $username
     * @param $tbkUser
     *
     * @throws Exception
     *
     * @return array
     */
    public function deleteInscription($username, $tbkUser)
    {
        try {
            if ($username == null || $tbkUser == null) {
                throw new Exception('El token tbkUser y el username son requerido');
            }

            $delInscription = $this->mallInscription->delete($tbkUser, $username);
            $this->log->logInfo('deleteInscription: '.json_encode($delInscription));

            return $delInscription;

        } catch (InscriptionFinishException $e) {
            $result = [
                'error'  => 'Error al eliminar una inscripción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $username
     * @param $tbkUser
     *
     * @throws Exception
     *
     * @return array
     */
    public function refundMallTransaction($buyOrder, $childCommerceCode, $childBuyOrder, $amount)
    {
        try {
            $refund = $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
            $this->log->logInfo('Refund Oneclick Mall tx: '.json_encode($refund));

            return $refund;

        } catch (MallRefundTransactiionException $e) {
            $result = [
                'error'  => 'Error reembolsar la transacción Mall',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $token
     * @param $amount
     *
     * @throws Exception
     *
     * @return array
     */

    public function refundTransaction($token, $amount)
    {
        try {
            $refund = $this->transaction->refund($token, $amount);
            $this->log->logInfo('Refund Webpay Plus tx: '.json_encode($refund));
            return $refund;

        }catch (TransactionRefundException $exception){
            $result = [
                'error'  => 'Error al intentar reembolsar la transacción',
                'detail' => $exception->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }
}
