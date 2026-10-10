<?php

declare(strict_types=1);

namespace Tueen\Database\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tueen\Database\DatabaseManager;

#[AsCommand(
    name: 'tueen:db:sync',
    description: 'Synchronizes the database schema with ORM entity metadata (Zero-Config mode)',
)]
final class SyncCommand extends Command
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Execute synchronization immediately without confirmation')
            ->addOption('dump-sql', 'd', InputOption::VALUE_NONE, 'Dump SQL statements instead of executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tueen Database: Zero-Config Schema Synchronizer');

        $synchronizer = $this->databaseManager->getAutoMigrator()->getSynchronizer();
        $pendingSql = $synchronizer->getPendingSql();

        if (count($pendingSql) === 0) {
            $io->success('The database schema is already in sync with all entity mappings!');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d pending schema statement(s):', count($pendingSql)));
        foreach ($pendingSql as $i => $sql) {
            $io->text(sprintf('<comment>[%d]</comment> %s', $i + 1, $sql));
        }

        if ($input->getOption('dump-sql')) {
            return Command::SUCCESS;
        }

        if (!$input->getOption('force') && !$io->confirm('Do you want to apply these schema changes to the database?', true)) {
            $io->warning('Operation cancelled by user.');
            return Command::SUCCESS;
        }

        $applied = $synchronizer->sync(safeMode: true);
        $io->success(sprintf('Successfully applied %d schema statements!', count($applied)));

        return Command::SUCCESS;
    }
}
