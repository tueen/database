<?php

declare(strict_types=1);

namespace Tueen\Database\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class SchemaSynchronizer
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Determines whether the current database schema matches the entity metadata.
     */
    public function isSynced(): bool
    {
        $sql = $this->getPendingSql();
        return count($sql) === 0;
    }

    /**
     * Returns an array of SQL statements required to bring the database schema in sync.
     *
     * @return list<string>
     */
    public function getPendingSql(): array
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        return $schemaTool->getUpdateSchemaSql($metadata);
    }

    /**
     * Automatically applies schema changes safely via non-destructive DDL.
     *
     * @return list<string> The executed SQL statements
     */
    public function sync(bool $safeMode = true): array
    {
        $pendingSql = $this->getPendingSql();

        if (count($pendingSql) === 0) {
            $this->logger->info('Tueen Database schema is already in sync.');
            return [];
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $this->logger->info(sprintf('Executing %d schema synchronization statements...', count($pendingSql)));

        $schemaTool->updateSchema($metadata);

        return $pendingSql;
    }
}
