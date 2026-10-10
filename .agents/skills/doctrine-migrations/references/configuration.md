# Doctrine Migrations Configuration Reference

This guide explains configuration options for Doctrine Migrations 3.x in standalone, Symfony, and framework-agnostic applications.

---

## 1. Complete `migrations.php` Settings

```php
<?php

declare(strict_types=1);

return [
    // Storage table configuration
    'table_storage' => [
        'table_name'                 => 'doctrine_migration_versions',
        'version_column_name'        => 'version',
        'version_column_length'      => 191,
        'executed_at_column_name'    => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],

    // Namespace-to-Directory mapping
    'migrations_paths' => [
        'DoctrineMigrations'           => __DIR__ . '/migrations',
        'App\Core\Migrations'          => __DIR__ . '/src/Core/Migrations',
    ],

    // If true, wraps the entire batch of migrations in a single transaction
    'all_or_nothing' => true,

    // If false, disables transactions inside individual migrations
    'transactional' => true,

    // Validates that the current database platform matches migration assertions
    'check_database_platform' => true,

    // Organize migration files in subdirectories: 'none', 'year', or 'year_and_month'
    'organize_migrations' => 'year_and_month',

    // Custom migration template class
    'custom_template' => null,
];
```

---

## 2. Bootstrapping Standalone CLI Runner (`cli-config.php`)

To run the Doctrine Migrations CLI (`vendor/bin/doctrine-migrations`) without a framework, provide a `cli-config.php` file in your project root:

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;

require_once __DIR__ . '/vendor/autoload.php';

// Load migrations configuration
$config = new PhpFile(__DIR__ . '/migrations.php');

// Setup DBAL connection
$connParams = [
    'driver'   => 'pdo_mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'dbname'   => getenv('DB_NAME') ?: 'app_db',
    'user'     => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
$connection = DriverManager::getConnection($connParams);

// Return DependencyFactory for CLI runner
return DependencyFactory::fromConnection($config, new ExistingConnection($connection));
```

If using Doctrine ORM as well, use `ExistingEntityManager`:
```php
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($entityManager));
```
