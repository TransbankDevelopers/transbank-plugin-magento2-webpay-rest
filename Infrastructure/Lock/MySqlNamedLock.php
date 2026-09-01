<?php

namespace Transbank\Webpay\Infrastructure\Lock;

use Magento\Framework\App\ResourceConnection;
use Transbank\Webpay\Exceptions\MySqlNamedLockException;

/**
 * Thin MySQL named lock adapter.
 *
 * It provides a reusable acquire/release primitive for flows that need to serialize work by key.
 */
class MySqlNamedLock
{
    private const GET_LOCK_TIMEOUT_SECONDS = 5;
    private const MAX_LOCK_NAME_LENGTH = 64;

    private $connection;

    public function __construct(ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }

    public function acquire(string $key): bool
    {
        $this->validateKeyLength($key);
        $result = $this->connection->fetchOne('SELECT GET_LOCK(?, ?)', [$key, self::GET_LOCK_TIMEOUT_SECONDS]);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo adquirir el lock de retorno de Webpay: error al consultar la base de datos.'
            );
        }

        return $result === '1';
    }

    public function release(string $key): bool
    {
        $this->validateKeyLength($key);
        $result = $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$key]);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo liberar el lock de retorno de Webpay: error al consultar la base de datos.'
            );
        }

        return $result === '1';
    }

    private function validateKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_LOCK_NAME_LENGTH) {
            throw new MySqlNamedLockException(
                'El nombre del lock excede el límite de ' . self::MAX_LOCK_NAME_LENGTH . ' caracteres de MySQL.'
            );
        }
    }
}
