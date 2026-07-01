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
    private const LOCK_PREFIX = 'transbank_webpay_lock_';
    private const GET_LOCK_TIMEOUT_SECONDS = 5;

    private $connection;

    public function __construct(ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }

    public function acquire(string $key): bool
    {
        $lockName = $this->buildLockName($key);
        $result = $this->connection->fetchOne('SELECT GET_LOCK(?, ?)', [$lockName, self::GET_LOCK_TIMEOUT_SECONDS]);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo adquirir el lock de retorno de Webpay: error al consultar la base de datos.'
            );
        }

        return $result === '1';
    }

    public function release(string $key): bool
    {
        $lockName = $this->buildLockName($key);
        $result = $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo liberar el lock de retorno de Webpay: error al consultar la base de datos.'
            );
        }

        return $result === '1';
    }

    private function buildLockName(string $key): string
    {
        return self::LOCK_PREFIX . substr(hash('sha256', $key), 0, 40);
    }
}
