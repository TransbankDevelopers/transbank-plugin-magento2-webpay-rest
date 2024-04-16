<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Transbank\Webpay\Model\Oneclick;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Model\QuoteManagement;
use Transbank\Webpay\Helper\PluginLogger;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\StoreManagerInterface;
use Transbank\Webpay\Model\Config\ConfigProvider;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\ResultInterface;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\WebpayOrderDataFactory;
use Magento\Sales\Model\Order\Payment\Transaction;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Magento\Framework\Controller\Result\JsonFactory;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;


/**
 * Controller for create Oneclick Inscription.
 */
class AuthorizeOneclick extends Action
{
    protected $configProvider;

    protected $responseCodeArray = [
        '-96' => 'Cancelaste la inscripción durante el formulario de Oneclick.',
        '-97' => 'La transacción ha sido rechazada porque se superó el monto máximo diario de pago.',
        '-98' => 'La transacción ha sido rechazada porque se superó el monto máximo de pago.',
        '-99' => 'La transacción ha sido rechazada porque se superó la cantidad máxima de pagos diarios.',
    ];

    private $cart;
    private $checkoutSession;
    private $resultJsonFactory;
    private $quoteManagement;
    private $storeManager;
    private $oneclickInscriptionDataFactory;
    private $log;
    private $webpayOrderDataFactory;
    protected $messageManager;

    /**
     * AuthorizeOneclick constructor.
     *
     * @param Context $context
     * @param Cart $cart
     * @param Session $checkoutSession
     * @param JsonFactory $resultJsonFactory
     * @param QuoteManagement $quoteManagement
     * @param StoreManagerInterface $storeManager
     * @param ConfigProvider $configProvider
     * @param OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
     * @param WebpayOrderDataFactory $webpayOrderDataFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Cart $cart,
        Session $checkoutSession,
        JsonFactory $resultJsonFactory,
        QuoteManagement $quoteManagement,
        StoreManagerInterface $storeManager,
        ConfigProvider $configProvider,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        WebpayOrderDataFactory $webpayOrderDataFactory,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quoteManagement = $quoteManagement;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->messageManager = $messageManager;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->log = new PluginLogger();
    }

    /**
     * @throws \Exception
     *
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $response = null;
        $orderStatusCanceled = $this->configProvider->getOneclickOrderErrorStatus();
        $orderStatusSuccess = $this->configProvider->getOneclickOrderSuccessStatus();
        $oneclickTitle = $this->configProvider->getOneclickTitle();

        try {
            $resultJson = $this->resultJsonFactory->create();

            if (isset($_POST['inscription'])) {
                $inscriptionId = intval($_POST['inscription']);
            } else {
                return $resultJson->setData(['status' => 'error', 'message' => 'Error autorizando transacción', 'flag' => 0]);
            }

            list($username, $tbkUser) = $this->getOneclickInscriptionData($inscriptionId);

            $config = $this->configProvider->getPluginConfigOneclick();

            $this->checkoutSession->restoreQuote();

            $quote = $this->cart->getQuote();

            $quote->getPayment()->importData(['method' => Oneclick::CODE]);
            $quote->collectTotals();
            $order = $this->getOrder();
            $grandTotal = round($order->getGrandTotal());

            $quoteId = $quote->getId();
            $orderId = $order->getId();

            $quote->save();

            $transbankSdkWebpay = new TransbankSdkWebpayRest($config);

            $this->log->logError(json_encode($order));

            $this->log->logError($config['CHILD_COMMERCE_CODE']);
            $this->log->logError($orderId);
            $this->log->logError($grandTotal);

            $buyOrder = "100000" . $orderId;
            $childBuyOrder = "200000" . $orderId;

            $details = [
                [
                    "commerce_code" => $config['CHILD_COMMERCE_CODE'],
                    "buy_order" => $childBuyOrder,
                    "amount" => $grandTotal,
                    "installments_number" => 1
                ]
            ];

            $response = $transbankSdkWebpay->authorizeTransaction($username, $tbkUser, $buyOrder, $details);
            $dataLog = ['customerId' => $username, 'orderId' => $orderId];

            if (isset($response->details) && $response->details[0]->responseCode == 0) {

                $webpayOrderData = $this->saveWebpayData(
                    $response,
                    $grandTotal,
                    OneclickInscriptionData::PAYMENT_STATUS_SUCCESS,
                    $orderId,
                    $quoteId
                );


                $this->checkoutSession->setLastQuoteId($quote->getId());
                $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
                $this->checkoutSession->setLastOrderId($order->getId());
                $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                $this->checkoutSession->setLastOrderStatus($order->getStatus());
                $this->checkoutSession->setGrandTotal($grandTotal);
                $this->checkoutSession->getQuote()->setIsActive(true)->save();
                $this->cart->getQuote()->setIsActive(true)->save();

                $orderLogs = '<h3>Pago autorizado exitosamente con ' . $oneclickTitle . '</h3><br>' . json_encode($dataLog);
                $payment = $order->getPayment();

                $payment->setLastTransId($response->details[0]->authorizationCode);
                $payment->setTransactionId($response->details[0]->authorizationCode);
                $payment->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $response->details[0]]);

                $order->setState($orderStatusSuccess)->setStatus($orderStatusSuccess);
                $order->addStatusToHistory($order->getStatus(), $orderLogs);
                $order->save();

                $this->checkoutSession->getQuote()->setIsActive(false)->save();

                $message = $this->getSuccessMessage($response, $oneclickTitle);
                $this->messageManager->addComplexSuccessMessage(
                    'successMessage',
                    [
                        'message' => $message
                    ]
                );
                return $resultJson->setData(['status' => 'success', 'response' => $response, '$webpayOrderData' => $webpayOrderData]);
            } else {
                $webpayOrderData = $this->saveWebpayData(
                    $config['CHILD_COMMERCE_CODE'],
                    $grandTotal,
                    OneclickInscriptionData::PAYMENT_STATUS_FAILED,
                    $orderId,
                    $quoteId,
                );

                $order->setStatus($orderStatusCanceled);
                $message = '<h3>Error en Inscripción con Oneclick</h3><br>' . json_encode($response);

                $order->addStatusToHistory($order->getStatus(), $message);
                $order->cancel();
                $order->save();

                $this->checkoutSession->restoreQuote();

                $message = $this->getRejectMessage($response, $oneclickTitle);
                $this->messageManager->addErrorMessage(__($message));

                // return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                return $resultJson->setData(['status' => 'error', 'response' => $response, 'flag' => 1]);
            }
        } catch (\Exception $e) {
            $message = 'Error al crear transacción: ' . $e->getMessage();

            $this->log->logError($message);
            $response = ['error' => $message];

            if ($order != null) {
                $order->cancel();
                $order->setStatus($orderStatusCanceled);
                $order->addStatusToHistory($order->getStatus(), $message);
                $order->save();
            }

            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultJson->setData(['status' => 'error', 'response' => $response, 'flag' => 2]);
        }
    }

    /**
     * @return |null
     */
    private function getOrder()
    {
        try {
            $orderId = $this->checkoutSession->getLastOrderId();
            if ($orderId == null) {
                return null;
            }

            $objectManager = ObjectManager::getInstance();

            return $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $inscriptionId
     *
     * @throws \Exception
     *
     * @return OneclickInscriptionData
     */
    protected function getOneclickInscriptionData($inscriptionId)
    {
        $oneclickInscriptionDataModel = $this->oneclickInscriptionDataFactory->create();
        $oneclickInscriptionData = $oneclickInscriptionDataModel->load($inscriptionId, 'id');
        $tbkUser = $oneclickInscriptionData->getTbkUser();
        $username = $oneclickInscriptionData->getUsername();

        return [$username, $tbkUser];
    }

    /**
     * @return string
     */
    protected function getOrderId()
    {
        return $this->checkoutSession->getLastRealOrderId();
    }

    /**
     * @param $buyOrder
     * @param $childBuyOrder
     * @param $commerceCode
     * @param $payment_status
     * @param $order_id
     * @param $quote_id
     *
     * @throws \Exception
     *
     * @return WebpayOrderData
     */
    protected function saveWebpayData($authorizeResponse, $amount, $payment_status, $order_id, $quote_id)
    {
        $webpayOrderData = $this->webpayOrderDataFactory->create();
        $webpayOrderData->setData([
            'buy_order'       => $authorizeResponse->getBuyOrder(),
            'child_buy_order' => $authorizeResponse->getDetails()[0]->getBuyOrder(),
            'commerce_code'   => $authorizeResponse->getDetails()[0]->getCommerceCode(),
            'child_commerce_code'   => $authorizeResponse->getDetails()[0]->getCommerceCode(),
            'payment_status'  => $payment_status,
            'order_id'        => $order_id,
            'quote_id'        => $quote_id,
            'amount'          => $amount,
            'metadata'        => json_encode($authorizeResponse),
        ]);
        $webpayOrderData->save();

        return $webpayOrderData;
    }

    protected function getSuccessMessage($transactionResult, $oneclickTitle)
    {
        if ($transactionResult->details[0]->responseCode == 0) {
            $transactionResponse = 'Transacci&oacute;n Aprobada';
        } else {
            $transactionResponse = 'Transacci&oacute;n Rechazada';
        }

        if ($transactionResult->details[0]->paymentTypeCode == 'VD') {
            $paymentType = 'Débito';
        } elseif ($transactionResult->details[0]->paymentTypeCode == 'VP') {
            $paymentType = 'Prepago';
        } else {
            $paymentType = 'Crédito';
        }


        $message = "
        <b>Detalles del pago {$oneclickTitle}</b>
        <div>
            • Respuesta de la Transacci&oacute;n: <b>{$transactionResponse}</b>
        </div>
        <div>
            • C&oacute;digo de la Transacci&oacute;n: <b>{$transactionResult->details[0]->responseCode}</b>
        </div>
        <div>
            • Monto: <b>$ {$transactionResult->details[0]->amount}</b>
        </div>
        <div>
            • Order de Compra: <b>{$transactionResult->details[0]->buyOrder}</b>
        </div>
        <div>
            • Fecha de la Transacci&oacute;n: <b>" . date('d-m-Y', strtotime($transactionResult->transactionDate)) . '</b>
        </div>
        <div>
            • Hora de la Transacci&oacute;n: <b>' . date('H:i:s', strtotime($transactionResult->transactionDate)) . "</b>
        </div>
        <div>
            • Tarjeta: <b>**** **** **** {$transactionResult->cardNumber}</b>
        </div>
        <div>
            • C&oacute;digo de autorizacion: <b>{$transactionResult->details[0]->authorizationCode}</b>
        </div>
        <div>
            • Tipo de Pago: <b>{$paymentType}</b>
        </div>
        ";

        return $message;
    }

    protected function getRejectMessage($transactionResult, $oneclickTitle)
    {
        if (isset($transactionResult)) {
            $message = "<h2>Autorizaci&oacute;n de transacci&oacute;n rechazada con {$oneclickTitle}</h2>
            <p>
                <br>
                <b>Respuesta de la Transacci&oacute;n: </b>{$this->responseCodeArray[$transactionResult->details[0]->responseCode]}<br>
                <b>Monto:</b> $ {$transactionResult->details[0]->amount}<br>
                <b>Order de Compra: </b> {$transactionResult->details[0]->buyOrder}<br>
                <b>Fecha de la Transacci&oacute;n: </b>" . date('d-m-Y', strtotime($transactionResult->transactionDate)) . '<br>
                <b>Hora de la Transacci&oacute;n: </b>' . date('H:i:s', strtotime($transactionResult->transactionDate)) . "<br>
                <b>Tarjeta: </b>**** **** **** {$transactionResult->cardNumber}<br>
            </p>";

            return $message;
        } else {
            if ($transactionResult->details[0]->status == 'ERROR') {
                $error = $transactionResult->details[0]->status;
                $detail = isset($transactionResult->details[0]) ? $transactionResult->details[0] : 'Sin detalles';
                $message = "<h2>Transacci&oacute;n fallida con Oneclick</h2>
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
