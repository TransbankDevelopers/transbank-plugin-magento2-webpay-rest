<?php

namespace Transbank\Webpay\Observer;

use Magento\Framework\Event\ObserverInterface;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Magento\Backend\Model\Session;
use Magento\Sales\Model\Service\CreditmemoService;
use Transbank\Webpay\Model\Webpay;
use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Oneclick\Exceptions\MallRefundTransactionException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionRefundException;

class RefundObserver implements ObserverInterface
{

    protected $_logger;
    protected $shippingMethod;
    protected $configProvider;
    protected $backendSession;
    protected $creditMemoService;

    public function __construct (
        \Psr\Log\LoggerInterface $logger,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        \Transbank\Webpay\Model\WebpayOrderDataFactory $webpayOrderDataFactory,
        \Magento\Backend\Model\Session $backendSession,
        \Magento\Sales\Model\Service\CreditmemoService $creditMemoService

    )
    {
        $this->_logger = $logger;
        $this->configProvider = $configProvider;
        $this->webpayOrderDataFactory = $webpayOrderDataFactory;
        $this->backendSession = $backendSession;
        $this->creditMemoService = $creditMemoService;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        $creditMemo = $observer->getEvent()->getCreditmemo();
        $order = $creditMemo->getOrder();
        $order_id = $order->getId();
        $grandTotal = $creditMemo->getGrandTotal();

        $this->_logger->debug(":: REFUND IN PROCCESS orden: ($order_id) - monto($grandTotal)");

        list($webpayOrderData,
            $commerceCode,
            $childCommerceCode,
            $amount,
            $metadata,
            $buyOrder,
            $childBuyOrder,
            $token) = $this->getTransaction($order);

        $this->_logger->debug(":: token ($token) child commerce code: ($childCommerceCode) metadata: ($metadata)");

        if (strlen($token) > 1) {

            $this->_logger->debug(":: anulación webpay plus");
            $config = $this->configProvider->getPluginConfig();
            $transaction = new TransbankSdkWebpayRest($config);

            $response = $transaction->refundTransaction($token, $grandTotal);

            $this->_logger->debug("Tipo de dato del response:");
            $this->_logger->debug(gettype($response));


            $this->_logger->debug($response);

            if($response->getResponseCode() == 0){

                try {
                    $this->creditMemoService->refund($creditMemo);
                    $this->backendSession->addSucces(json_encode($response). ' '. 'El reembolso se ejecutó exitosamente');
                    $webpayOrderData->setMetadata(json_encode($response). ' ' .$metadata);
                    $webpayOrderData->setPaymentStatus(WebpayOrderData::PAYMENT_STATUS_REFUNDED);
                    $webpayOrderData->save();
                    $order->addStatusHistoryComment(json_encode($response), true);
                    $order->save();
                }catch (TransactionRefundException $exception){
                    $this->_logger->debug(json_encode($response));
                    $this->backendSession->addError('Se produjo un error al crear el Reembolso: ' . $exception->getMessage());
                }
            }else{
                $this->_logger->debug(json_encode($response));
                $this->backendSession->addError('La solicitud de reembolso falló. Por favor, inténtelo de nuevo más tarde.');
                return;
            }

        }else {

            $this->_logger->debug(":: Anulación oneclick");
            $config = $this->configProvider->getPluginConfigOneclick();
            $mallTransaction = new TransbankSdkWebpayRest($config);

            $response = $mallTransaction->refundMallTransaction($buyOrder, $childCommerceCode, $childBuyOrder, $grandTotal);

            $this->_logger->debug("Tipo de dato del response:");
            $this->_logger->debug(gettype($response));

            if($response->getResponseCode() == 0){
                $this->_logger->debug(json_encode($response));
                try {
                    $this->creditMemoService->refund($creditMemo);
                    $this->backendSession->addSucces(json_encode($response). ' '. 'El reembolso se ejecutó exitosamente');
                    $webpayOrderData->setMetadata(json_encode($response). ' ' .$metadata);
                    $webpayOrderData->setPaymentStatus(OneclickInscriptionData::PAYMENT_STATUS_REVERSED);
                    $webpayOrderData->save();
                    $order->addStatusHistoryComment(json_encode($response), true);
                    $order->save();
                }catch (MallRefundTransactionException $exception){
                    $this->_logger->debug($response);
                    $this->backendSession->addError('Se produjo un error al crear el Reembolso: ' . $exception->getMessage());
                }
            }

            $this->_logger->debug($response);

        }
    }

    /**
     * @param $tokenWs
     *
     * @return array
     */
    private function getTransaction($order)
    {
        $webpayOrderDataModel = $this->webpayOrderDataFactory->create();
        $webpayOrderData = $webpayOrderDataModel->load($order->getId(), 'order_id');
        if($webpayOrderData->isEmpty()){
            $webpayOrderData = $webpayOrderDataModel->load($order->getIncrementId(), 'order_id');
        }
        $token = $webpayOrderData->getToken();
        $commerceCode = $webpayOrderData->getCommerceCode();
        $childCommerceCode = $webpayOrderData->getChildCommerceCode();
        $amount = floatval($webpayOrderData->getAmount());
        $metadata = $webpayOrderData->getMetadata();
        $buyOrder = $webpayOrderData->getBuyOrder();
        $childBuyOrder = $webpayOrderData->getChildBuyOrder();

        return [$webpayOrderData, $commerceCode, $childCommerceCode, $amount, $metadata, $buyOrder, $childBuyOrder, $token];
    }


}
