# Extending Entities & Custom Migrations

Tueen Database is built for modular bot ecosystems. Third-party packages, plugins, or your own bot application can easily register new entities, custom tables, and custom migrations.

---

## 🧩 Registering Custom Entity Paths

If your bot defines custom entities (e.g. `App\Entity\QuizQuestion`, `App\Entity\TicketMessage`):

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

$db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));

// Register additional entity directory
$db->registerEntityPath(__DIR__ . '/src/Entity');

// The zero-config auto-migrator immediately detects new entities!
$db->syncSchema();
```

---

## 📜 Registering Custom Migration Paths

If you prefer versioned migration files for your custom tables:

```php
// Register a custom migration namespace and directory
$db->registerMigrationPath('App\\Migrations', __DIR__ . '/migrations');
```

Any class extending `Doctrine\Migrations\AbstractMigration` located in that folder will be automatically discovered and integrated into `AutoMigrator` and CLI tools.

---

## ✍️ Example Custom Migration

```php
<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bot_quizzes custom table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('bot_quizzes');
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->addColumn('question', 'string', ['length' => 255]);
        $table->addColumn('reward_amount', 'decimal', ['precision' => 36, 'scale' => 18]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('bot_quizzes');
    }
}
```

---

## 🧭 Next Steps

- Integrate with **[Symfony DI & Events](/guide/symfony-integration)**.
- Browse the full **[Entity Models Catalog](/reference/entities)**.
