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
    name: 'tueen:db:diff',
    description: 'Inspect pending SQL schema differences between entities and live database',
)]
final class DiffCommand extends Command
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tueen Database: Schema Diff Inspector');

        $pendingSql = $this->databaseManager->getAutoMigrator()->getPendingSql();

        if (count($pendingSql) === 0) {
            $io->success('Database is in sync. No schema diff detected.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d pending SQL statement(s) needed to synchronize:', count($pendingSql)));
        foreach ($pendingSql as $sql) {
            $io->writeln("<info>{$sql};</info>");
        }

        return Command::SUCCESS;
    }
}
