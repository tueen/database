<?php

declare(strict_types=1);

namespace Tueen\Database\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tueen\Database\Event\MigrationExecutedEvent;

final class AutoMigrator
{
    private bool $hasRun = false;
    private LoggerInterface $logger;
    private ?EventDispatcherInterface $eventDispatcher;
    private SchemaSynchronizer $synchronizer;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MigrationRegistry $registry,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();
        $this->synchronizer = new SchemaSynchronizer($entityManager, $this->logger);
    }

    /**
     * Executes automatic declarative zero-config schema synchronization
     * if the database schema is not in sync.
     *
     * @param bool $force If true, executes even if already checked in this instance.
     * @return list<string> The list of SQL statements applied
     */
    public function autoMigrate(bool $force = false): array
    {
        if ($this->hasRun && !$force) {
            return [];
        }

        $this->hasRun = true;

        $startTime = hrtime(true);
        $executedSql = $this->synchronizer->sync(safeMode: true);

        if (count($executedSql) > 0) {
            $durationMs = (int) round((hrtime(true) - $startTime) / 1e6);
            $this->logger->info(sprintf('Zero-config migration applied %d statements in %d ms.', count($executedSql), $durationMs));

            $this->eventDispatcher?->dispatch(new MigrationExecutedEvent(
                version: 'zero-config-sync',
                direction: 'up',
                executionTimeMs: $durationMs,
            ));
        }

        return $executedSql;
    }

    public function resetProcessState(): void
    {
        $this->hasRun = false;
    }

    /**
     * @return list<string>
     */
    public function getPendingSql(): array
    {
        return $this->synchronizer->getPendingSql();
    }

    public function isSynced(): bool
    {
        return $this->synchronizer->isSynced();
    }

    public function getSynchronizer(): SchemaSynchronizer
    {
        return $this->synchronizer;
    }

    public function getRegistry(): MigrationRegistry
    {
        return $this->registry;
    }
}
