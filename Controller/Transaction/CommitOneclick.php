<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Sales\Model\Order;
use Transbank\Webpay\Model\LogHandler;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;

/**
 * Controller for commit transaction Oneclick.
 */
class CommitOneclick extends \Magento\Framework\App\Action\Action
{
    protected $responseCodeArray = [
        '-96' => 'Cancelaste la inscripción durante el formulario de Oneclick.',
        '-97' => 'La transacción ha sido rechazada porque se superó el monto máximo diario de pago.',
        '-98' => 'La transacción ha sido rechazada porque se superó el monto máximo de pago.',
        '-99' => 'La transacción ha sido rechazada porque se superó la cantidad máxima de pagos diarios.',
    ];

    protected $configProvider;

    protected $quoteRepository;
    protected $cart;
    protected $checkoutSession;
    protected $resultJsonFactory;
    protected $resultRawFactory;
    protected $oneclickInscriptionDataFactory;
    protected $log;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resultRawFactory = $resultRawFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->log = new LogHandler();
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
                        $message = $this->getRejectMessage($this->commitResponseToArray($inscriptionResult), $oneclickTitle);
                    } else {
                        $message = "Cancelaste la inscripción durante el formulario de {$oneclickTitle}.";
                    }

                    $this->messageManager->addError(__($message));

                    $OneclickInscriptionData->save();

                    $order->cancel();
                    $order->save();
                    $order->setStatus($orderStatusCanceled);

                    $order->addStatusToHistory($order->getStatus(), json_encode($inscriptionResult));
                    $order->save();

                    $this->checkoutSession->restoreQuote();

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            } else {
                $inscriptionResult = json_decode($OneclickInscriptionData->getMetadata(), true);

                if ($status == OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
                    $message = "¡Tarjeta inscrita exitosamente!";
                    $this->messageManager->addSuccess(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
                } elseif ($status == OneclickInscriptionData::PAYMENT_STATUS_FAILED) {
                    $OneclickInscriptionData->setStatus(OneclickInscriptionData::PAYMENT_STATUS_FAILED);
                    $OneclickInscriptionData->save();

                    $this->checkoutSession->restoreQuote();
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
            // $message = $this->responseCodeArray[$transactionResult['responseCode']];
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
    
}
