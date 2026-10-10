# CLI Tooling (`tueen-db`)

Tueen Database ships with a built-in command-line console executable: **`bin/tueen-db`**.

---

## 🛠️ Available Commands

| Command | Purpose |
| :--- | :--- |
| `php bin/tueen-db tueen:db:validate` | Validates ORM mapping metadata and schema sync |
| `php bin/tueen-db tueen:db:status` | Displays engine status, connection parameters, and registered migrations |
| `php bin/tueen-db tueen:db:diff` | Dumps pending SQL statements required to synchronize schema |
| `php bin/tueen-db tueen:db:sync` | Interactively or forcefully synchronizes schema (Zero-Config mode) |
| `php bin/tueen-db tueen:cache:clear` | Clears database cache and in-memory options runtime cache |

---

## 💻 Command Reference & Usage

### Validating Mappings & Schema (`tueen:db:validate`)

Ensures all Doctrine attributes, entity mappings, relationships, and foreign keys are valid:

```bash
php bin/tueen-db tueen:db:validate
```

```
Tueen Database: Schema & Mapping Validator
==========================================

 [OK] The mapping files are correct.
 [OK] The database schema is in sync with the mapping files.
```

---

### Inspecting Engine Status (`tueen:db:status`)

Displays the active connection driver, database platform, and schema status:

```bash
php bin/tueen-db tueen:db:status
```

```
Tueen Database: Engine Status
=============================

 --------------- ---------------------------------------- 
  Driver          pdo_mysql                               
  Platform        Doctrine\DBAL\Platforms\MySQL80Platform 
  Database Name   telegram_bot                            
  Auto-Migrate    Enabled (Zero-Config)                   
  Dev Mode        No                                      
  Cache Adapter   redis                                   
 --------------- ---------------------------------------- 

 [OK] Database Schema: 100% In Sync
```

---

### Synchronizing Schema (`tueen:db:sync`)

Applies non-destructive schema differences without running manual migration files:

```bash
# Preview and confirm interactively:
php bin/tueen-db tueen:db:sync

# Force execution in CI/CD deployment scripts:
php bin/tueen-db tueen:db:sync --force

# Dump SQL to stdout without executing:
php bin/tueen-db tueen:db:sync --dump-sql
```

---

## 🧭 Next Steps

- Learn how to **[Extend Entities & Schemas](/guide/extending)** with custom bot plugins.
- Integrate with **[Symfony DI & Events](/guide/symfony-integration)**.
