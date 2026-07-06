<?php

namespace Transbank\Webpay\Model\Repository;

use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData\CollectionFactory;

/**
 * Class OneclickInscriptionDataRepository
 * Repository for OneclickInscriptionData model
 */
class OneclickInscriptionDataRepository
{
    protected $oneclickInscriptionDataFactory;
    protected $collectionFactory;

    /**
     * Constructor
     *
     * @param OneclickInscriptionDataFactory
     * @param CollectionFactory
     */
    public function __construct(
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get OneclickInscriptionData by id
     *
     * @param int $id The inscription id
     *
     * @return OneclickInscriptionData
     */
    public function getById(int $id): OneclickInscriptionData
    {
        return $this->oneclickInscriptionDataFactory->create()->load($id, 'id');
    }

    /**
     * Get OneclickInscriptionData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @return OneclickInscriptionData
     */
    public function getByToken(string $token): OneclickInscriptionData
    {
        return $this->oneclickInscriptionDataFactory->create()->load($token, 'token');
    }

    /**
     * Persist a OneclickInscriptionData entity
     *
     * @param OneclickInscriptionData $oneclickInscriptionData The entity to persist
     *
     * @return OneclickInscriptionData
     */
    public function save(OneclickInscriptionData $oneclickInscriptionData): OneclickInscriptionData
    {
        $oneclickInscriptionData->save();

        return $oneclickInscriptionData;
    }

    /**
     * Get the active (successfully finished) inscriptions for a given customer
     *
     * @param int $customerId The customer id
     *
     * @return array
     */
    public function getActiveInscriptionsByCustomerId(int $customerId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect(['id', 'username', 'card_type', 'card_number'])
            ->addFieldToFilter('user_id', $customerId)
            ->addFieldToFilter('status', OneclickInscriptionData::PAYMENT_STATUS_SUCCESS);

        $inscriptions = [];

        foreach ($collection as $item) {
            $inscriptions[] = $item->getData();
        }

        return $inscriptions;
    }
}
