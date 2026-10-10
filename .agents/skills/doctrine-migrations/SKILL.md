---
name: doctrine-migrations
description: >-
  Expert guidance and best practices for Doctrine Migrations. Use when configuring
  migrations (migrations.php), generating schema diffs from ORM entities (migrations:diff),
  running and rolling back migrations (migrations:migrate), writing safe up() and down()
  database migrations with AbstractMigration, and automating migrations in CI/CD pipelines.
---

# Doctrine Migrations Skill

This skill provides comprehensive instructions, configuration files, and production runbooks for **Doctrine Migrations** (version 3.x+), based on the [official Doctrine Migrations Documentation](https://www.doctrine-project.org/projects/doctrine-migrations/en/current/).

---

## 1. Core Principles & Safety Rules

1. **Never Modify Deployed Migrations**:
   - Once a migration is merged into `main` or executed in staging/production, it is immutable. Always write a new forward migration to correct or change schema.
2. **Always Implement `down()`**:
   - Provide symmetrical, working rollback logic in `down()`. Always verify both `up` and `down` locally before committing.
3. **Enable `all_or_nothing` Transactions**:
   - Run migrations in a single atomic transaction whenever possible (`'all_or_nothing' => true`). On platforms supporting transactional DDL (PostgreSQL, SQLite), any error will roll back the entire migration batch.
4. **Use Safety Assertions**:
   - Protect against wrong environments or wrong database platforms using `$this->abortIf()` or `$this->skipIf()`.
5. **Always Run `--dry-run` or Review Diff First**:
   - Inspect the generated SQL before applying migrations to production.

---

## 2. Configuration Setup (`migrations.php`)

Place `migrations.php` in your project root or config directory:

```php
<?php

declare(strict_types=1);

return [
    'table_storage' => [
        'table_name'                 => 'doctrine_migration_versions',
        'version_column_name'        => 'version',
        'version_column_length'      => 191,
        'executed_at_column_name'    => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],

    'migrations_paths' => [
        'DoctrineMigrations' => __DIR__ . '/migrations',
    ],

    'all_or_nothing'            => true,
    'transactional'             => true,
    'check_database_platform'   => true,
    'organize_migrations'       => 'none', // or 'year', 'year_and_month'
    'connection'                => null,
    'em'                        => null,
];
```

---

## 3. Essential CLI Commands Runbook

| Action | Command | Purpose |
| :--- | :--- | :--- |
| **Check Status** | `vendor/bin/doctrine-migrations migrations:status` | Shows executed vs pending migrations |
| **Generate Diff** | `vendor/bin/doctrine-migrations migrations:diff` | Compares ORM entities with DB and generates migration |
| **Generate Blank** | `vendor/bin/doctrine-migrations migrations:generate` | Creates an empty migration class |
| **Migrate All** | `vendor/bin/doctrine-migrations migrations:migrate --no-interaction` | Runs all pending migrations |
| **Dry Run** | `vendor/bin/doctrine-migrations migrations:migrate --dry-run` | Prints SQL without executing |
| **Dump SQL** | `vendor/bin/doctrine-migrations migrations:migrate --write-sql=/path` | Exports migration SQL to a file |
| **Rollback One** | `vendor/bin/doctrine-migrations migrations:migrate prev --no-interaction` | Rolls back the latest applied migration |
| **Rollback to Version**| `vendor/bin/doctrine-migrations migrations:migrate 'DoctrineMigrations\Version2026...'` | Target specific version |

---

## 4. Writing an `AbstractMigration` Class

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and user_tokens tables with security indexes';
    }

    public function up(Schema $schema): void
    {
        // Platform safety check
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration requires PostgreSQL.'
        );

        $this->addSql('
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL,
                roles JSONB NOT NULL DEFAULT \'["ROLE_USER"]\',
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');

        $this->addSql('
            CREATE TABLE user_tokens (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');

        $this->addSql('CREATE INDEX idx_user_tokens_lookup ON user_tokens (user_id, token)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This migration requires PostgreSQL.'
        );

        $this->addSql('DROP TABLE user_tokens');
        $this->addSql('DROP TABLE users');
    }
}
```

---

## 5. Non-Destructive Data Migrations

When migrating existing data along with schema changes:
1. Make new columns `NULLABLE` first.
2. Backfill existing rows using `$this->addSql('UPDATE users SET ...')`.
3. Alter column to `NOT NULL` in a subsequent step or migration.

```php
public function up(Schema $schema): void
{
    // Step 1: Add column as nullable
    $this->addSql('ALTER TABLE users ADD display_name VARCHAR(100) DEFAULT NULL');

    // Step 2: Backfill existing data from email prefix
    $this->addSql('UPDATE users SET display_name = SUBSTRING_INDEX(email, \'@\', 1) WHERE display_name IS NULL');

    // Step 3: Enforce NOT NULL constraint
    $this->addSql('ALTER TABLE users MODIFY display_name VARCHAR(100) NOT NULL');
}
```

---

## 6. Automating Migrations in CI/CD Deployment

In deployment scripts (GitHub Actions, GitLab CI, Docker entrypoints), always run:

```bash
# 1. Ensure migrations are up to date without prompting
php vendor/bin/doctrine-migrations migrations:migrate --no-interaction --allow-no-migration

# 2. Verify schema matches ORM metadata completely (fails build if developer forgot to create migration!)
php vendor/bin/doctrine-orm-cli orm:schema-tool:validate --skip-sync
```

---

## 7. References & Examples Navigation

- **Configuration File Guide**: [references/configuration.md](./references/configuration.md) - Deep dive on connection providers, dependency injection, and container setups.
- **CLI Commands Guide**: [references/cli_commands.md](./references/cli_commands.md) - Full options flags, sync-metadata, rollup, and version management.
- **Production Config Example**: [examples/migrations.php](./examples/migrations.php) - Production-ready configuration.
- **Migration Template Example**: [examples/Version20261002000000.php](./examples/Version20261002000000.php) - Fully implemented migration with DDL, data backfill, and rollback.
