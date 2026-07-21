<?php

namespace Transbank\Webpay\Model\Repository;

use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData\CollectionFactory;

/**
 * Class OneclickInscriptionDataRepository
 * Repository for OneclickInscriptionData model
 */
class OneclickInscriptionDataRepository
{
    protected $collectionFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Get OneclickInscriptionData by id
     *
     * @param int $id The inscription id
     *
     * @throws OneclickInscriptionNotFoundException When no inscription matches the given id
     *
     * @return OneclickInscriptionData
     */
    public function getById(int $id): OneclickInscriptionData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('id', $id);
        $inscription = $collection->getFirstItem();

        if (!$inscription->getId()) {
            throw new OneclickInscriptionNotFoundException();
        }

        return $inscription;
    }

    /**
     * Get OneclickInscriptionData by Webpay token
     *
     * @param string $token The Webpay token
     *
     * @throws OneclickInscriptionNotFoundException When no inscription matches the given token
     *
     * @return OneclickInscriptionData
     */
    public function getByToken(string $token): OneclickInscriptionData
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('token', $token);
        $inscription = $collection->getFirstItem();

        if (!$inscription->getId()) {
            throw new OneclickInscriptionNotFoundException();
        }

        return $inscription;
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
            ->addFieldToFilter('status', OneclickInscriptionData::PAYMENT_STATUS_SUCCESS)
            ->setOrder('id', 'ASC');

        $inscriptions = [];

        foreach ($collection as $item) {
            $inscriptions[] = $item->getData();
        }

        return $inscriptions;
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
}
