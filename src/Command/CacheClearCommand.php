<?php

declare(strict_types=1);

namespace Tueen\Database\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Repository\OptionRepository;

#[AsCommand(
    name: 'tueen:cache:clear',
    description: 'Clears the database cache and in-memory options runtime cache',
)]
final class CacheClearCommand extends Command
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tueen Database: Cache Cleaner');

        // Clear runtime options cache
        OptionRepository::clearRuntimeCache();

        // Clear database cache table
        $this->databaseManager->cache()->clear();

        $io->success('Database cache and runtime caches cleared successfully.');

        return Command::SUCCESS;
    }
}
