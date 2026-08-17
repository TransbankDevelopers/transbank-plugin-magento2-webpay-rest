<?php

namespace Transbank\Webpay\Model\Service;

use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Model\Repository\WebpayOrderDataRepository;

/**
 * Class WebpayOrderDataService
 * Business rules and data-access orchestration for WebpayOrderData.
 */
class WebpayOrderDataService
{
    protected $webpayOrderDataRepository;

    /**
     * Constructor
     *
     * @param WebpayOrderDataRepository $webpayOrderDataRepository
     */
    public function __construct(
        WebpayOrderDataRepository $webpayOrderDataRepository
    ) {
        $this->webpayOrderDataRepository = $webpayOrderDataRepository;
    }

    /**
     * Get a WebpayOrderData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @throws \Transbank\Webpay\Exceptions\WebpayOrderDataNotFoundException When no record matches the given token
     *
     * @return WebpayOrderData
     */
    public function getByToken(string $token): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->getByToken($token);
    }

    /**
     * Get a WebpayOrderData by buy order
     *
     * @param string $buyOrder The buy order
     *
     * @throws \Transbank\Webpay\Exceptions\WebpayOrderDataNotFoundException When no record matches the given buy order
     *
     * @return WebpayOrderData
     */
    public function getByBuyOrder(string $buyOrder): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->getByBuyOrder($buyOrder);
    }

    /**
     * Get a WebpayOrderData by order ID
     *
     * @param string $orderId The order ID
     *
     * @throws \Transbank\Webpay\Exceptions\WebpayOrderDataNotFoundException When no record matches the given order ID
     *
     * @return WebpayOrderData
     */
    public function getByOrderId(string $orderId): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->getByOrderId($orderId);
    }

    /**
     * Get a WebpayOrderData by order ID and quote ID
     *
     * @param int $orderId The order ID
     * @param int $quoteId The quote ID
     *
     * @return WebpayOrderData
     */
    public function getByOrderIdAndQuoteId(int $orderId, int $quoteId): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->getByOrderIdAndQuoteId($orderId, $quoteId);
    }

    /**
     * Create and persist a new WebpayOrderData record.
     *
     * @param array $data The data to initialize the entity with
     *
     * @return WebpayOrderData
     */
    public function create(array $data): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->create($data);
    }

    /**
     * Update the payment status of a WebpayOrderData record and persist it.
     *
     * @param WebpayOrderData $webpayOrderData The entity being updated
     * @param string $paymentStatus The new payment status
     *
     * @return void
     */
    public function updatePaymentStatus(WebpayOrderData $webpayOrderData, string $paymentStatus): void
    {
        $webpayOrderData->setPaymentStatus($paymentStatus);
        $this->webpayOrderDataRepository->save($webpayOrderData);
    }
}
