<?php

namespace Transbank\Webpay\Controller\Transaction;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Sales\Model\Order;
use Transbank\Webpay\Exceptions\InvalidRequestException;
use Transbank\Webpay\Helper\PluginLogger;
use Transbank\Webpay\Model\TransbankSdkWebpayRest;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\Service\OneclickInscriptionService;
use Transbank\Webpay\Model\Service\OrderService;
use Transbank\Webpay\Model\Service\QuoteService;
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
    protected $resultRawFactory;
    protected $oneclickInscriptionService;
    protected $orderService;
    protected $log;
    protected $messageManager;
    protected $customerSession;
    private $quoteService;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Transbank\Webpay\Model\Config\ConfigProvider $configProvider,
        OneclickInscriptionService $oneclickInscriptionService,
        OrderService $orderService,
        QuoteService $quoteService,
        CustomerSession $customerSession
    ) {
        parent::__construct($context);

        $this->checkoutSession = $checkoutSession;
        $this->resultRawFactory = $resultRawFactory;
        $this->messageManager = $context->getMessageManager();
        $this->configProvider = $configProvider;
        $this->oneclickInscriptionService = $oneclickInscriptionService;
        $this->orderService = $orderService;
        $this->log = new PluginLogger();
        $this->quoteService = $quoteService;
        $this->customerSession = $customerSession;
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

            if (!is_string($tbkToken)) {
                throw new \Exception('Token no encontrado');
            }

            $oneclickInscriptionData = $this->getInscriptionByToken($tbkToken);
            $order = $this->orderService->getById($oneclickInscriptionData->getOrderId());
            $status = $oneclickInscriptionData->getStatus();

            if ($status == OneclickInscriptionData::PAYMENT_STATUS_WATING) {
                $transbankSdkWebpay = new TransbankSdkWebpayRest($config);
                $inscriptionResult = $transbankSdkWebpay->finishInscription($tbkToken);

                if ($this->oneclickInscriptionService->resolveInscriptionFinishResult($oneclickInscriptionData, $inscriptionResult)) {
                    $message = "Tarjeta inscrita exitosamente";
                    $this->messageManager->addSuccess(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                } else {
                    $statusFields = $this->getInscriptionResponseFields($inscriptionResult);
                    $message = $this->getRejectMessage($statusFields, $oneclickTitle);
                    $this->messageManager->addError(__($message));

                    $historyComment = $this->createHistoryComment(
                        'Inscripción rechazada',
                        $statusFields,
                        true
                    );

                    $this->orderService->cancel($order, $orderStatusCanceled, $historyComment);
                    $this->quoteService->reactivateAfterOrderCancelByQuoteId($order->getQuoteId());

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
            } else {
                $inscriptionResult = json_decode($oneclickInscriptionData->getMetadata(), true);

                if ($status == OneclickInscriptionData::PAYMENT_STATUS_SUCCESS) {
                    $message = "¡Tarjeta inscrita exitosamente!";
                    $this->messageManager->addSuccess(__($message));

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                } elseif ($status == OneclickInscriptionData::PAYMENT_STATUS_FAILED) {
                    $this->oneclickInscriptionService->setInscriptionAsFailed($oneclickInscriptionData);

                    $this->quoteService->reactivateAfterOrderCancelByQuoteId($order->getQuoteId());
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

    /**
     * @param string $tbkToken
     *
     * @return OneclickInscriptionData
     *
     * @throws InvalidRequestException When the token belongs to another customer
     */
    private function getInscriptionByToken($tbkToken)
    {
        $oneclickInscriptionData = $this->oneclickInscriptionService->getByToken($tbkToken);
        $userId = $oneclickInscriptionData->getUserId();
        $customerId = $this->customerSession->getCustomerId();

        if (!$this->oneclickInscriptionService->isOwnedByCustomer($userId, $customerId)) {
            throw new InvalidRequestException('La tarjeta inscrita indicada no existe.');
        }

        return $oneclickInscriptionData;
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
            $this->orderService->cancel($order, $orderStatusCanceled, $message);
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    protected function getRejectMessage(array $transactionResult, $oneclickTitle)
    {
        $hasResponseCode = isset($transactionResult['responseCode']);
        $oneclickTitle = htmlspecialchars($oneclickTitle, ENT_QUOTES, 'UTF-8');

        if ($hasResponseCode && isset($this->responseCodeArray[$transactionResult['responseCode']])) {
            $message = "<b>Transacci&oacute;n rechazada por {$oneclickTitle}</b>
                <div>
                    {$this->responseCodeArray[$transactionResult['responseCode']]}
                </div>";
        } elseif ($hasResponseCode) {
            $message = self::REJECT_MESSAGE;
        } elseif (isset($transactionResult['error'])) {
            $error = htmlspecialchars($transactionResult['error'], ENT_QUOTES, 'UTF-8');
            $detail = htmlspecialchars($transactionResult['detail'] ?? 'Sin detalles', ENT_QUOTES, 'UTF-8');
            $message = "<b>Transacci&oacute;n fallida por {$oneclickTitle}</b>
                <div>
                    {$error}
                </div>
                <div>
                    <b>Mensaje: </b>{$detail}
                </div>";
        } else {
            $message = '<b>Transacci&oacute;n Fallida</b>';
        }

        return $message;
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
