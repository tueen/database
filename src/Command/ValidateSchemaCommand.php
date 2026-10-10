<?php

declare(strict_types=1);

namespace Tueen\Database\Command;

use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tueen\Database\DatabaseManager;

#[AsCommand(
    name: 'tueen:db:validate',
    description: 'Validates Doctrine ORM mapping metadata and live database synchronization',
)]
final class ValidateSchemaCommand extends Command
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tueen Database: Schema & Mapping Validator');

        $em = $this->databaseManager->getEntityManager();
        $validator = new SchemaValidator($em);

        $mappingErrors = $validator->validateMapping();
        if (count($mappingErrors) > 0) {
            $io->error('Entity mapping errors detected:');
            foreach ($mappingErrors as $className => $errors) {
                $io->section($className);
                $io->listing($errors);
            }
            return Command::FAILURE;
        }

        $io->success('[OK] The mapping files are correct.');

        if ($validator->schemaInSyncWithMetadata()) {
            $io->success('[OK] The database schema is in sync with the mapping files.');
            return Command::SUCCESS;
        }

        $io->warning('[FAIL] The database schema is not in sync with the current mapping file.');
        return Command::FAILURE;
    }
}
