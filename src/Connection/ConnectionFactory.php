<?php

declare(strict_types=1);

namespace Tueen\Database\Connection;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Tueen\Database\Config\ConnectionConfig;

final class ConnectionFactory
{
    public static function createConnection(ConnectionConfig $config, ?Configuration $dbalConfig = null): Connection
    {
        if ($config->driver === 'pdo_sqlite' && $config->path !== null && $config->path !== ':memory:') {
            $dir = dirname($config->path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0o777, true);
            }
        }

        $params = $config->toDbalParams();
        $dbalConfig ??= new Configuration();

        $connection = DriverManager::getConnection($params, $dbalConfig);

        // Configure platform-specific hardening
        self::configurePlatform($connection, $config->driver);

        return $connection;
    }

    private static function configurePlatform(Connection $connection, string $driver): void
    {
        if ($driver === 'pdo_sqlite') {
            // Apply SQLite high-performance concurrency pragma settings
            try {
                $connection->executeStatement('PRAGMA foreign_keys = ON;');
                $connection->executeStatement('PRAGMA busy_timeout = 5000;');
                // Only enable WAL if not an in-memory database
                $params = $connection->getParams();
                if (empty($params['memory'])) {
                    $connection->executeStatement('PRAGMA journal_mode = WAL;');
                }
            } catch (\Throwable) {
                // Ignore pragma failures in edge testing environments
            }
        }
    }
}
