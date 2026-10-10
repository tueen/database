# Multi-Database Setup

Tueen Database is completely database-agnostic. It abstracts dialect differences so that your bot code, queries, and repositories run identically across SQLite, MySQL, MariaDB, and PostgreSQL.

---

## 🗄️ Supported Database Platforms

| Database Platform | Best For | Recommended Driver | Zero-Config Support |
| :--- | :--- | :--- | :--- |
| **SQLite (In-Memory)** | Automated testing, unit tests, ephemeral bots | `pdo_sqlite` | Full |
| **SQLite (File)** | Small to medium Telegram bots, single-server setups | `pdo_sqlite` | Full (Auto directory creation + WAL mode) |
| **MySQL 8.0+ / MariaDB** | High-throughput production, large bot userbases | `pdo_mysql` | Full (`utf8mb4_unicode_ci`) |
| **PostgreSQL 14+** | Enterprise bots, advanced JSONB indexing | `pdo_pgsql` | Full |

---

## 💻 Configuration Examples

### SQLite (In-Memory & File)

SQLite requires zero external server setup:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

// In-Memory SQLite (great for unit and functional tests)
$db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));

// File-based SQLite (creates the directory and enables WAL mode automatically)
$db = DatabaseManager::create(DatabaseConfig::createSqlite(__DIR__ . '/var/bot.sqlite'));
```

::: tip SQLite Concurrency
When using file-based SQLite, Tueen Database automatically applies:
- `PRAGMA foreign_keys = ON;`
- `PRAGMA busy_timeout = 5000;`
- `PRAGMA journal_mode = WAL;` (Write-Ahead Logging)

This allows multiple concurrent bot processes or webhook requests to read and write without `database is locked` errors.
:::

---

### MySQL / MariaDB

For high-volume production deployments:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

$db = DatabaseManager::create(
    DatabaseConfig::createMysql(
        dbname: 'telegram_bot',
        user: 'bot_admin',
        password: 'secure_password',
        host: '127.0.0.1',
        port: 3306,
        charset: 'utf8mb4',
    )
);
```

---

### PostgreSQL

For enterprise deployments requiring advanced relational constraints:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

$db = DatabaseManager::create(
    DatabaseConfig::createPostgres(
        dbname: 'telegram_bot',
        user: 'postgres',
        password: 'secret_password',
        host: '127.0.0.1',
        port: 5432,
    )
);
```

---

## 🔐 Environment Variables (`.env`)

Tueen Database supports zero-config loading from `.env` files via `CredentialManager`:

```ini
# .env
DB_DRIVER=pdo_mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=telegram_bot
DB_USER=bot_admin
DB_PASSWORD=secret_password
DB_AUTO_MIGRATE=true
```

When `config/database.php` is loaded or CLI commands are run, the configuration is resolved automatically from the environment.

---

## 🧭 Next Steps

- Explore **[Chats & Contacts Management](/guide/chats-and-contacts)**.
- Learn about the **[Wallets & Treasury Ledger](/guide/wallets-and-treasury)**.
