<?php

namespace Transbank\Webpay\Model\Service;

use Transbank\Webpay\Model\WebpayOrderData;
use Transbank\Webpay\Model\Repository\WebpayOrderDataRepository;
use Transbank\Webpay\Exceptions\EcommerceException;

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
     * @param bool $throwIfNotFound When true, throws instead of returning null
     *
     * @throws EcommerceException When not found and $throwIfNotFound is true
     *
     * @return WebpayOrderData|null Null when no record matches the given token
     */
    public function getByToken(string $token, bool $throwIfNotFound = false): ?WebpayOrderData
    {
        $webpayOrderData = $this->webpayOrderDataRepository->getByToken($token);

        if ($webpayOrderData === null && $throwIfNotFound) {
            throw new EcommerceException('No se encontró la transacción de Webpay para el token: ' . $token);
        }

        return $webpayOrderData;
    }

    /**
     * Get a WebpayOrderData by buy order
     *
     * @param string $buyOrder The buy order
     * @param bool $throwIfNotFound When true, throws instead of returning null
     *
     * @throws EcommerceException When not found and $throwIfNotFound is true
     *
     * @return WebpayOrderData|null Null when no record matches the given buy order
     */
    public function getByBuyOrder(string $buyOrder, bool $throwIfNotFound = false): ?WebpayOrderData
    {
        $webpayOrderData = $this->webpayOrderDataRepository->getByBuyOrder($buyOrder);

        if ($webpayOrderData === null && $throwIfNotFound) {
            throw new EcommerceException('No se encontró la transacción de Webpay para la orden de compra: ' . $buyOrder);
        }

        return $webpayOrderData;
    }

    /**
     * Get a WebpayOrderData by order ID
     *
     * @param string $orderId The order ID
     * @param bool $throwIfNotFound When true, throws instead of returning null
     *
     * @throws EcommerceException When not found and $throwIfNotFound is true
     *
     * @return WebpayOrderData|null Null when no record matches the given order ID
     */
    public function getByOrderId(string $orderId, bool $throwIfNotFound = false): ?WebpayOrderData
    {
        $webpayOrderData = $this->webpayOrderDataRepository->getByOrderId($orderId);

        if ($webpayOrderData === null && $throwIfNotFound) {
            throw new EcommerceException('No se encontró la transacción de Webpay para order_id: ' . $orderId);
        }

        return $webpayOrderData;
    }

    /**
     * Get a WebpayOrderData by order ID and quote ID
     *
     * @param int $orderId The order ID
     * @param int $quoteId The quote ID
     * @param bool $throwIfNotFound When true, throws instead of returning null
     *
     * @throws EcommerceException When not found and $throwIfNotFound is true
     *
     * @return WebpayOrderData|null Null when no record matches the given order ID and quote ID
     */
    public function getByOrderIdAndQuoteId(int $orderId, int $quoteId, bool $throwIfNotFound = false): ?WebpayOrderData
    {
        $webpayOrderData = $this->webpayOrderDataRepository->getByOrderIdAndQuoteId($orderId, $quoteId);

        if ($webpayOrderData === null && $throwIfNotFound) {
            throw new EcommerceException('No se encontró la transacción Oneclick para order_id: ' . $orderId);
        }

        return $webpayOrderData;
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
     * Update a WebpayOrderData record with the given fields and persist it.
     *
     * @param WebpayOrderData $webpayOrderData The entity being updated
     * @param array $data The fields to update
     *
     * @return WebpayOrderData
     */
    public function update(WebpayOrderData $webpayOrderData, array $data): WebpayOrderData
    {
        return $this->webpayOrderDataRepository->update($webpayOrderData, $data);
    }
}
