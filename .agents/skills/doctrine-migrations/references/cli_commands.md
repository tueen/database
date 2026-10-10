# Doctrine Migrations CLI Commands Reference

Complete command reference for the `doctrine-migrations` command-line utility.

---

## 1. Daily Development Commands

### Check Current Migration Status
Displays current version, executed migrations, and pending migrations:
```bash
php vendor/bin/doctrine-migrations migrations:status
```

### Auto-Generate Diff from ORM Entities
Compares entity metadata with current database schema and creates a new migration file:
```bash
php vendor/bin/doctrine-migrations migrations:diff
```

### Create Blank Migration
Generates an empty migration skeleton class:
```bash
php vendor/bin/doctrine-migrations migrations:generate
```

### Execute Pending Migrations
Applies all pending migrations:
```bash
# Interactive mode (prompts confirmation)
php vendor/bin/doctrine-migrations migrations:migrate

# Non-interactive mode (for deployment scripts / CI)
php vendor/bin/doctrine-migrations migrations:migrate --no-interaction
```

---

## 2. Rollback & Troubleshooting Commands

### Rollback to Previous Version
Rolls back the most recent migration:
```bash
php vendor/bin/doctrine-migrations migrations:migrate prev --no-interaction
```

### Rollback to Initial State (Undo All)
```bash
php vendor/bin/doctrine-migrations migrations:migrate first --no-interaction
```

### Dry Run (Preview SQL)
Outputs the exact SQL queries that would be executed without touching the database:
```bash
php vendor/bin/doctrine-migrations migrations:migrate --dry-run
```

### Export SQL to File
Dumps migration SQL into a file for DBA review or manual deployment:
```bash
php vendor/bin/doctrine-migrations migrations:migrate --write-sql=/path/to/migration.sql
```

---

## 3. Metadata & Version Table Manipulation

### Manually Mark Migration as Executed (Without Running SQL)
Useful when fixing corrupted states or importing legacy schemas:
```bash
php vendor/bin/doctrine-migrations migrations:version 'DoctrineMigrations\Version20261002000000' --add
```

### Manually Remove Migration Record from Table
```bash
php vendor/bin/doctrine-migrations migrations:version 'DoctrineMigrations\Version20261002000000' --delete
```

### Sync Metadata Table
Updates the migration tracking table schema if migrations configuration was updated:
```bash
php vendor/bin/doctrine-migrations migrations:sync-metadata-storage
```
