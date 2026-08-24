<?php

namespace Transbank\Webpay\Model;

use Transbank\Webpay\Exceptions\MissingArgumentException;
use Transbank\Webpay\Exceptions\TransbankCreateException;
use Transbank\Webpay\Exceptions\TransbankException;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionFinishException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionDeleteException;
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
     * @var Transaction
     */
    public ?Transaction $transaction = null;

    /**
     * @var MallInscription
     */
    public ?MallInscription $mallInscription = null;

    /**
     * @var MallTransaction
     */
    public ?MallTransaction $mallTransaction = null;

    /**
     * Raw configuration array used to build SDK clients.
     * @var array<string,mixed>
     * */
    private ?array $config = null;

    /**
     * TransbankSdkWebpayRest constructor.
     *
     * @param $config
     * @param $product
     */
    public function __construct($config)
    {
        $this->log = new PluginLogger();

        $this->config = is_array($config) ? $config : null;
    }

    private function requireConfig(): array
    {
        if ($this->config === null) {
            throw new MissingArgumentException('La configuración es requerida.');
        }

        return $this->config;
    }

    /**
     * Builds an SDK Options instance based on environment and credentials.
     *
     * If the environment is TEST, integration credentials are used.
     * If the environment is PRODUCTION, API key and commerce code are required.
     *
     * @param string $environment Environment name ('TEST' or 'PRODUCTION')
     * @param string $defaultApiKey Integration API key (used in TEST)
     * @param string $defaultCommerceCode Integration commerce code (used in TEST)
     * @param string|null $apiKey Production API key (required in PROD)
     * @param string|null $commerceCode Production commerce code (required in PROD)
     *
     * @throws MissingArgumentException If required production credentials are missing
     *
     * @return Options Configured SDK options instance
     */
    private function buildOptions(
        string $environment,
        string $defaultApiKey,
        string $defaultCommerceCode,
        ?string $apiKey,
        ?string $commerceCode,
    ): Options {
        $isProd = strtoupper(trim($environment)) !== 'TEST';

        if (!$isProd) {
            return new Options($defaultApiKey, $defaultCommerceCode, Options::ENVIRONMENT_INTEGRATION);
        }

        $apiKey = $apiKey ?? '';
        $commerceCode = $commerceCode ?? '';

        if ($apiKey === '' || $commerceCode === '') {
            throw new MissingArgumentException('Credenciales de configuración incompletas para el entorno actual.');
        }

        return new Options($apiKey, $commerceCode, Options::ENVIRONMENT_PRODUCTION);
    }

    /**
     * Initializes the Webpay Plus Transaction client if not already created.
     *
     * Uses integration credentials in TEST and production credentials otherwise.
     *
     * @return void
     */
    private function configureTransactionCredentials(): void
    {
        if (!is_null($this->transaction)) {
            return;
        }

        $config = $this->requireConfig();

        $options = $this->buildOptions(
            $config['ENVIRONMENT'] ?? 'TEST',
            WebpayPlus::INTEGRATION_API_KEY,
            WebpayPlus::INTEGRATION_COMMERCE_CODE,
            $config['API_KEY'] ?? null,
            $config['COMMERCE_CODE'] ?? null,
        );

        $this->transaction = new Transaction($options);
    }

    /**
     * Initializes the Oneclick MallInscription client if not already created.
     *
     * Uses integration credentials in TEST and production credentials otherwise.
     *
     * @return void
     */
    private function configureMallInscriptionCredentials(): void
    {

        if (!is_null($this->mallInscription)) {
            return;
        }

        $config = $this->requireConfig();

        $options = $this->buildOptions(
            $config['ENVIRONMENT'] ?? 'TEST',
            Oneclick::INTEGRATION_API_KEY,
            Oneclick::INTEGRATION_COMMERCE_CODE,
            $config['API_KEY'] ?? null,
            $config['COMMERCE_CODE'] ?? null,
        );

        $this->mallInscription = new MallInscription($options);
    }

    /**
     * Initializes the Oneclick MallTransaction client if not already created.
     *
     * Uses integration credentials in TEST and production credentials otherwise.
     *
     * @return void
     */
    private function configureMallTransactionCredentials(): void
    {

        if (!is_null($this->mallTransaction)) {
            return;
        }

        $config = $this->requireConfig();

        $options = $this->buildOptions(
            $config['ENVIRONMENT'] ?? 'TEST',
            Oneclick::INTEGRATION_API_KEY,
            Oneclick::INTEGRATION_COMMERCE_CODE,
            $config['API_KEY'] ?? null,
            $config['COMMERCE_CODE'] ?? null,
        );

        $this->mallTransaction = new MallTransaction($options);
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

            $this->configureTransactionCredentials();
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

            $this->configureTransactionCredentials();
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

            $this->configureMallInscriptionCredentials();
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

            $this->configureMallInscriptionCredentials();
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

        $this->configureMallTransactionCredentials();
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
     * @return bool
     */
    public function deleteInscription(string $username, string $tbkUser): bool
    {
        try {
            if (empty($username) || empty($tbkUser)) {
                throw new MissingArgumentException('El token tbkUser y el username son requerido');
            }

            $this->configureMallInscriptionCredentials();
            return $this->mallInscription->delete($tbkUser, $username);
        } catch (InscriptionDeleteException $e) {
            throw new TransbankException('Error al eliminar la inscripción: ' . $e->getMessage(), $e->getCode(), $e);
        }
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
        $this->configureMallTransactionCredentials();
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
        $this->configureTransactionCredentials();
        return $this->transaction->refund($token, $amount);
    }
}
