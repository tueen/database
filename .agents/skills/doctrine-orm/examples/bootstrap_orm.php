<?php

declare(strict_types=1);

namespace App\Database\OrmExamples;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;

/**
 * Modern Doctrine ORM 3.x / 2.x Bootstrap Factory.
 */
final class EntityManagerFactory
{
    /**
     * @param list<string> $entityPaths Directories containing entity classes
     * @param array<string, mixed> $connectionParams DBAL connection parameters
     */
    public static function create(
        array $entityPaths,
        array $connectionParams = [],
        bool $isDevMode = true
    ): EntityManagerInterface {
        // 1. Setup PHP 8 Attributes Metadata Configuration
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $entityPaths,
            isDevMode: $isDevMode
        );

        // 2. Default SQLite in-memory or fallback params
        $defaultParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $params = array_merge($defaultParams, $connectionParams);

        // 3. Establish DBAL Connection
        $connection = DriverManager::getConnection($params, $config);

        // 4. Return new EntityManager instance
        return new EntityManager($connection, $config);
    }
}
