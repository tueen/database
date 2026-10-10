<?php

declare(strict_types=1);

namespace Tueen\Database\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tueen\Database\DatabaseManager;

#[AsCommand(
    name: 'tueen:db:status',
    description: 'Displays current database connection, schema status, and registered migrations',
)]
final class StatusCommand extends Command
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tueen Database: Engine Status');

        $config = $this->databaseManager->getConfig();
        $conn = $this->databaseManager->getConnection();
        $platform = $conn->getDatabasePlatform()::class;

        $io->definitionList(
            ['Driver' => $config->connection->driver],
            ['Platform' => $platform],
            ['Database Name' => $config->connection->dbname ?? ($config->connection->path ?? 'memory')],
            ['Auto-Migrate' => $config->autoMigrate ? '<info>Enabled (Zero-Config)</info>' : '<comment>Disabled</comment>'],
            ['Dev Mode' => $config->devMode ? '<comment>Yes</comment>' : '<info>No</info>'],
            ['Cache Adapter' => $config->cacheDriver],
        );

        $isSynced = $this->databaseManager->getAutoMigrator()->isSynced();
        if ($isSynced) {
            $io->success('Database Schema: 100% In Sync');
        } else {
            $pendingCount = count($this->databaseManager->getAutoMigrator()->getPendingSql());
            $io->warning(sprintf('Database Schema: Out of Sync (%d pending statements). Run `tueen:db:sync` to resolve.', $pendingCount));
        }

        $migrationClasses = $this->databaseManager->getMigrationRegistry()->discoverMigrationClasses();
        $io->section(sprintf('Registered Migration Classes (%d):', count($migrationClasses)));
        if (count($migrationClasses) > 0) {
            $rows = [];
            foreach ($migrationClasses as $cls) {
                $rows[] = [$cls];
            }
            $io->table(['Class'], $rows);
        } else {
            $io->text('No custom migration classes found.');
        }

        return Command::SUCCESS;
    }
}
