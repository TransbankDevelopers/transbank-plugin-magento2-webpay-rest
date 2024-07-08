<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Transbank\Webpay\Helper\QuoteHelper;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse;

/**
 * Controller for commit transaction Oneclick.
 */
class CommitOneclick extends \Magento\Framework\App\Action\Action
{
    private const REJECT_MESSAGE = "
        <b>Inscripción rechazada por Oneclick</b>
        <div>
            No ha sido posible realizar la inscripción, por favor reintenta con otro medio de pago.
        </div>
        ";
    protected $responseCodeArray = [
        '-96' => 'Cancelaste la inscripción durante el formulario de Oneclick.',
        '-97' => 'La transacción ha sido rechazada porque se superó el monto máximo diario de pago.',
        '-98' => 'La transacción ha sido rechazada porque se superó el monto máximo de pago.',
        '-99' => 'La transacción ha sido rechazada porque se superó la cantidad máxima de pagos diarios.',
    ];

    private $responseFieldDescription = [
        'responseCode' => 'Código de respuesta',
        'tbkUser' => 'TBK User',
        'authorizationCode' => 'Código de autorización',
        'cardType' => 'Tipo de tarjeta',
        'cardNumber' => 'Número de tarjeta'
    ];

    protected $configProvider;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $resultRawFactory;
    protected $oneclickInscriptionDataFactory;
    protected $log;
    protected $messageManager;
    private $quoteHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        QuoteHelper $quoteHelper
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->log = new PluginLogger();
        $this->quoteHelper = $quoteHelper;
    }

    /**
     * @Override
     */
    public function execute()
    {
        $config = $this->configProvider->getPluginConfigOneclick();
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $inscriptionResult = [];
        $oneclickTitle = $this->configProvider->getOneclickTitle();

        try {

            $tbkToken = $_POST['TBK_TOKEN'] ?? $_GET['TBK_TOKEN'] ?? null;

            if (is_null($tbkToken)) {
                throw new \Exception('Token no encontrado');
            }

            list($OneclickInscriptionData, $order) = $this->getOrderByToken($tbkToken);

            $status = $OneclickInscriptionData->getStatus();
            if ($status == OneclickInscriptionData::PAYMENT_STATUS_WATING) {
                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
                $inscriptionResult = $transbankSdkWebpay->finishInscription($tbkToken);
                $OneclickInscriptionData->setMetadata(json_encode($inscriptionResult));

                if (isset($inscriptionResult->tbkUser) && isset($inscriptionResult->responseCode) && $inscriptionResult->responseCode == 0) {
                    $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_SUCCESS);
                    $OneclickInscriptionData->setResponseCode($inscriptionResult->responseCode);
                    $OneclickInscriptionData->setTbkUser($inscriptionResult->tbkUser);
                    $OneclickInscriptionData->setAuthorizationCode($inscriptionResult->authorizationCode);
                    $OneclickInscriptionData->setCardType($inscriptionResult->cardType);
                    $OneclickInscriptionData->setCardNumber($inscriptionResult->cardNumber);

                    $OneclickInscriptionData->save();

                    $message = "Tarjeta inscrita exitosamente";
                    $this->messageManager->addSuccess(__($message));


                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                } else {
                    $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);
                    if (isset($inscriptionResult->responseCode)) {
                        $OneclickInscriptionData->setResponseCode($inscriptionResult->responseCode);
                    }

                    $this->messageManager->addError(__(self::REJECT_MESSAGE));

                    $OneclickInscriptionData->save();

                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);

                    $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());

                    $statusFields = $this->getInscriptionResponseFields($inscriptionResult);
                    $historyComment = $this->createHistoryComment(
                        'Inscripción rechazada',
                        $statusFields,
                        true
                    );

                    $order->addStatusToHistory($order->getStatus(), $historyComment);
                    $order->save();

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            } else {
                $inscriptionResult = json_decode($OneclickInscriptionData->getMetadata(), true);

                if ($status == OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
                    $message = "¡Tarjeta inscrita exitosamente!";
                    $this->messageManager->addSuccess(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                } elseif ($status == OneclickInscriptionData::PAYMENT_STATUS_FAILED) {
                    $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);
                    $OneclickInscriptionData->save();

                    $this->quoteHelper->processQuoteForCancelOrder($order->getQuoteId());
                    $message = $this->getRejectMessage($inscriptionResult, $oneclickTitle);
                    $this->messageManager->addError(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            }
        } catch (\Exception $e) {
            $order = isset($order) ? $order : null;

            return $this->errorOnConfirmation($e, $order, $orderStatusCanceled);
        }
    }

    protected function toRedirect($url, $data)
    {
        $response = $this->resultRawFactory->create();
        $content = "<form action='$url' method='POST' name='webpayForm'>";
        foreach ($data as $name => $value) {
            $content .= "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
        }
        $content .= '</form>';
        $content .= "<script language='JavaScript'>document.webpayForm.submit();</script>";
        $response->setContents($content);

        return $response;
    }

    protected function commitResponseToArray($response)
    {
        return [
            'responseCode'          => $response->responseCode,
            'tbkUser'               => $response->tbkUser,
            'authorizationCode'     => $response->authorizationCode,
            'cardType'              => $response->cardType,
            'cardNumber'            => $response->cardNumber,
        ];
    }

    protected function getOrder($orderId)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        return $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
    }

    /**
     * @param $tokenWs
     *
     * @return array
     */
    private function getOrderByToken($tbkToken)
    {
        $oneclickInscriptionDataModel = $this->oneclickInscriptionDataFactory->create();
        $oneclickInscriptionData = $oneclickInscriptionDataModel->load($tbkToken, 'token');
        $orderId = $oneclickInscriptionData->getOrderId();
        $order = $this->getOrder($orderId);

        return [$oneclickInscriptionData, $order];
    }

    /**
     * @param \Exception $e
     * @param $order
     * @param $orderStatusCanceled
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function errorOnConfirmation(\Exception $e, $order, $orderStatusCanceled)
    {
        $message = 'Error al crear inscripción: '.$e->getMessage();
        $this->log->logError($message);
        $this->checkoutSession->restoreQuote();
        $this->messageManager->addError(__($message));
        if ($order != null && $order->getState() != Order::STATE_PROCESSING) {
            $order->cancel();
            $order->save();
            $order->setStatus($orderStatusCanceled);
            $order->addStatusToHistory($order->getStatus(), $message);
            $order->save();
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    protected function getRejectMessage(array $transactionResult, $oneclickTitle)
    {
        if (isset($transactionResult['responseCode'])) {
            $message = "<h2>Transacci&oacute;n rechazada con {$oneclickTitle}</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$this->responseCodeArray[$transactionResult['responseCode']]}<br>
            </p>";

            return $message;
        } else {
            if (isset($transactionResult['error'])) {
                $error = $transactionResult['error'];
                $detail = isset($transactionResult['detail']) ? $transactionResult['detail'] : 'Sin detalles';
                $message = "<h2>Transacci&oacute;n fallida con {$oneclickTitle}</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$error}<br>
                <b>Mensaje: </b>{$detail}
            </p>";

                return $message;
            } else {
                $message = '<h2>Transacci&oacute;n Fallida</h2>';

                return $message;
            }
        }
    }

    /**
     * @param string $commentTitle An string used as comment title
     * @param array $data An array of key => value to add on comment body
     * @param bool $skipNullValues indicates if null values should be skipped
     *
     * @return string
     */
    private function createHistoryComment( $commentTitle, $data, $skipNullValues = false ): string {
        $title = '<strong>' . $commentTitle . '</strong><br><br>';
        $items = '';
        foreach ($data as $key => $value) {
            if ($skipNullValues && $value == null) {
                continue;
            }
            $fieldDescription = $this->responseFieldDescription[$key] ?? $key;
            $items .= '<strong>' . $fieldDescription . '</strong>: ' . $value . '<br>';
        }
        return $title . $items;
    }

    /**
     * @param array|InscriptionFinishResponse $inscriptionResponse
     *
     * @return array
     */
    private function getInscriptionResponseFields( $inscriptionResponse ): array {
        if ( $inscriptionResponse instanceof InscriptionFinishResponse ){
            return [
                'responseCode' => $inscriptionResponse->getResponseCode(),
                'tbkUser' => $inscriptionResponse->getTbkUser(),
                'authorizationCode' => $inscriptionResponse->getAuthorizationCode(),
                'cardType' => $inscriptionResponse->getCardType(),
                'cardNumber' => $inscriptionResponse->getCardNumber()
            ];
        }
        return $inscriptionResponse;
    }

}
