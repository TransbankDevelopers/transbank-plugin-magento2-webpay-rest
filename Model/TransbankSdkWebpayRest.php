<?php

namespace Transbank\Webpay\Model;

use Transbank\Webpay\Exceptions\MissingArgumentException;
use Transbank\Webpay\Exceptions\TransbankCreateException;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionFinishException;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;

/**
 * Class TransbankSdkWebpayRest.
 */
class TransbankSdkWebpayRest
{

    /**
     * @var PluginLogger
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
        $this->log = new PluginLogger();
        if (isset($config)) {
            $environment = isset($config['ENVIRONMENT']) ? $config['ENVIRONMENT'] : 'TEST';

            $this->transaction = new WebpayPlus\Transaction();
            $this->mallInscription = new Oneclick\MallInscription();
            $this->mallTransaction = new Oneclick\MallTransaction();

            $this->log->logInfo('Environment: ' . json_encode($environment));

            if ($environment != 'TEST') {
                $this->transaction->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
                $this->mallInscription->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
                $this->mallTransaction->configureForProduction($config['COMMERCE_CODE'], $config['API_KEY']);
            }
        }
    }

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     *
     * @throws TransbankCreateException
     *
     * @return array
     */
    public function createTransaction($amount, $sessionId, $buyOrder, $returnUrl)
    {
        $result = [];

        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('createTransaction - amount: ' . $amount . ', sessionId: ' . $sessionId .
                ', buyOrder: ' . $buyOrder . ', txDate: ' . $txDate . ', txTime: ' . $txTime);

            $createResult = $this->transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

            $this->log->logInfo('createTransaction - createResult: ' . json_encode($createResult));
            if (isset($createResult) && isset($createResult->url) && isset($createResult->token)) {
                $result = [
                    'url'      => $createResult->url,
                    'token_ws' => $createResult->token,
                ];
            } else {
                throw new TransbankCreateException('No se ha creado la transacción para, amount: ' . $amount . ', sessionId: ' . $sessionId . ', buyOrder: ' . $buyOrder);
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
     * @throws MissingArgumentException
     *
     * @return array|\Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse
     */
    public function commitTransaction($tokenWs)
    {
        try {
            if ($tokenWs == null) {
                throw new MissingArgumentException('El token webpay es requerido');
            }

            $transaction = $this->transaction->commit($tokenWs);

            $this->log->logInfo('commitTransaction: ' . json_encode($transaction));
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
     * @throws TransbankCreateException
     *
     * @return array
     */
    public function createInscription($username, $email, $responseUrl)
    {
        $result = [];

        try {
            $this->log->logInfo('initInscription - Username: ' . $username . ', email: ' . $email .
                ', responseUrl: ' . $responseUrl);

            $initResult = $this->mallInscription->start($username, $email, $responseUrl);

            $this->log->logInfo('createInscription - initResult: ' . json_encode($initResult));
            if (isset($initResult) && isset($initResult->token) && isset($initResult->urlWebpay)) {
                $result = [
                    'token'      => $initResult->token,
                    'urlWebpay' => $initResult->urlWebpay,
                ];
            } else {
                throw new TransbankCreateException('No se ha creado la inscripción para, username: ' . $username . ', email: ' . $email . ', responseUrl: ' . $responseUrl);
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
     * @throws MissingArgumentException
     *
     * @return array
     */
    public function finishInscription($tbkToken)
    {
        try {
            $this->log->logInfo('getInscriptonResult - tokenWs: ' . $tbkToken);
            if ($tbkToken == null) {
                throw new MissingArgumentException('El token tokenWs es requerido');
            }

            $inscription = $this->mallInscription->finish($tbkToken);
            $this->log->logInfo('finishInscription: ' . json_encode($inscription));

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
     * This method authorize a Oneclick transaction.
     *
     * @param string $username The username of the inscription.
     * @param string $tbkUser  The tbk_user of the inscription.
     * @param string $buyOrder The buy order.
     * @param array $details  The transactions details.
     *
     * @throws MissingArgumentException Thrown when username or tbk_user is null.
     *
     * @return MallTransactionAuthorizeResponse The authorization response.
     */
    public function authorizeTransaction(
        string $username,
        string $tbkUser,
        string $buyOrder,
        array $details
    ): MallTransactionAuthorizeResponse {
        if ($username == null || $tbkUser == null) {
            throw new MissingArgumentException('El token tbkUser y el username son requeridos');
        }

        $transaction = $this->mallTransaction->authorize($username, $tbkUser, $buyOrder, $details);
        $this->log->logInfo('authorizeTransaction: ' . json_encode($transaction));

        return $transaction;
    }

    /**
     * @param $username
     * @param $tbkUser
     *
     * @throws MissingArgumentException
     *
     * @return array
     */
    public function deleteInscription($username, $tbkUser)
    {
        try {
            if ($username == null || $tbkUser == null) {
                throw new MissingArgumentException('El token tbkUser y el username son requerido');
            }

            $delInscription = $this->mallInscription->delete($tbkUser, $username);
            $this->log->logInfo('deleteInscription: ' . json_encode($delInscription));

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
     * @param string $buyOrder
     * @param string $childCommerceCode
     * @param string $childBuyOrder
     * @param int $amount
     *
     * @throws \Transbank\Webpay\Oneclick\Exceptions\MallRefundTransactionException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse
     */
    public function refundOneClickTransaction(
        string $buyOrder,
        string $childCommerceCode,
        string $childBuyOrder,
        int $amount
    ): \Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse {
        return $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
    }

    /**
     * @param string $token
     * @param int $amount
     *
     * @throws \Transbank\Webpay\WebpayPlus\Exceptions\TransactionRefundException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse
     */

    public function refundWebpayPlusTransaction(
        string $token,
        int $amount
    ): \Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse {
        return $this->transaction->refund($token, $amount);
    }
}
