<?php

namespace Transbank\Webpay\Model\Repository;

use Transbank\Webpay\Exceptions\OneclickInscriptionNotFoundException;
use Transbank\Webpay\Model\OneclickInscriptionData;
use Transbank\Webpay\Model\OneclickInscriptionDataFactory;
use Transbank\Webpay\Model\ResourceModel\OneclickInscriptionData\CollectionFactory;

/**
 * Class OneclickInscriptionDataRepository
 * Repository for OneclickInscriptionData model
 */
class OneclickInscriptionDataRepository
{
    private const WRITABLE_FIELDS = [
        'status',
        'token',
        'username',
        'email',
        'user_id',
        'order_id',
        'environment',
        'commerce_code',
        'metadata',
        'tbk_user',
        'response_code',
        'authorization_code',
        'card_type',
        'card_number',
    ];

    protected $collectionFactory;
    private $oneclickInscriptionDataFactory;

    /**
     * Constructor
     *
     * @param CollectionFactory $collectionFactory
     * @param OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        OneclickInscriptionDataFactory $oneclickInscriptionDataFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->oneclickInscriptionDataFactory = $oneclickInscriptionDataFactory;
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

    /**
     * Build and persist a OneclickInscriptionData entity from a set of fields.
     *
     * @param array $data The fields to set. Keys outside WRITABLE_FIELDS are silently discarded.
     *
     * @return OneclickInscriptionData
     */
    public function create(array $data): OneclickInscriptionData
    {
        $inscription = $this->oneclickInscriptionDataFactory->create();
        $inscription->addData(array_intersect_key($data, array_flip(self::WRITABLE_FIELDS)));

        return $this->save($inscription);
    }

    /**
     * Update a OneclickInscriptionData entity with the given fields and persist it.
     *
     * @param OneclickInscriptionData $inscription The entity to update
     * @param array $data The fields to update. Keys outside WRITABLE_FIELDS are silently discarded.
     *
     * @return OneclickInscriptionData
     */
    public function update(OneclickInscriptionData $inscription, array $data): OneclickInscriptionData
    {
        $inscription->addData(array_intersect_key($data, array_flip(self::WRITABLE_FIELDS)));
        $inscription->save();

        return $inscription;
    }
}
