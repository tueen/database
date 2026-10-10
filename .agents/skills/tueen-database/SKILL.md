---
name: tueen-database
description: >-
  Expert guidance and best practices for developing and extending Tueen Database (Tueen\Database).
  Use when designing entities with PHP 8.4 property hooks, working with zero-config auto-migrations,
  managing multi-currency bot ledgers, querying through domain repositories, and maintaining multi-database compatibility.
---

# Tueen Database Skill & Development Guide

This skill governs best practices, code generation patterns, and architecture rules for **`Tueen\Database`**, the zero-config, high-throughput database engine for Telegram bots and the Tueen ecosystem.

---

## 🚀 1. Quick Architecture & Bootstrapping

To instantiate Tueen Database in a standalone application:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

// 1. Fast SQLite Setup (Zero-Config auto-migration enabled by default)
$db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));

// Or MySQL:
// $db = DatabaseManager::create(DatabaseConfig::createMysql(dbname: 'telegram_bot', user: 'root', password: 'secret'));

// 2. Access repositories directly
$chat = $db->chats()->findOrCreate(
    chatId: 123456789,
    username: '@telegram_user',
    name: 'Alice',
);

// 3. Multi-currency ledger transaction
$db->wallets()->credit($chat, '25.000000000000000000', 'TON', 'welcome_bonus');
```

---

## 🪝 2. PHP 8.4 Entity Conventions

Every entity in `Tueen\Database\Entity` must follow these rules:

1. **Property Hooks:**
   - Compute virtual values using `get => ...`:
     ```php
     public bool $isActive {
         get => $this->expiresAt > new \DateTimeImmutable();
     }
     ```
   - Normalize values using `set => ...`:
     ```php
     public ?string $username {
         set (?string $value) => $value !== null && ($val = strtolower(ltrim(trim($value), '@'))) !== '' ? $val : null;
     }

     public string $phoneNumber {
         set (string $value) => (string) preg_replace('/\D+/', '', $value);
     }
     ```
   - **Username Collision Auto-Release:** When registering or updating a chat with a username that was previously held by another chat, `ChatRepository::findOrCreate()` automatically releases (nulls) the username from the previous owner.
2. **Asymmetric Visibility:**
   - Use `public private(set)` for properties that should be readable publicly but only mutable internally:
     ```php
     public private(set) int $id;
     public private(set) string $publicId;
     public private(set) \DateTimeImmutable $createdAt;
     ```
3. **Telegram Chat ID Sign:**
   - Telegram Chat IDs are **signed 64-bit BigInts** (user IDs > 0, group/supergroup/channel IDs < 0). Always map them as `Types::BIGINT` and never unsigned.

---

## 🔄 3. Zero-Config & Lazy Auto-Migration (WordPress Style)

Tueen Database does not require running CLI migrations during standard development or bot startup:

- When `DatabaseManager` boots or any repository is queried, `AutoMigrator` checks if the live database schema matches the entity definitions.
- If differences exist, `SchemaSynchronizer` executes non-destructive DDL (`CREATE TABLE`, `ADD COLUMN`, `ADD INDEX`, etc.) in safe mode automatically.
- **Third-Party Extensions:**
  Custom bot modules or plugins can register extra entity directories or migration namespaces:
  ```php
  $db->registerEntityPath('/path/to/plugin/entities');
  $db->registerMigrationPath('Plugin\\Migrations', '/path/to/plugin/migrations');
  ```

---

## 💰 4. Ledger & Financial Precision

- All financial calculations (`WalletBalance`, `WalletTransaction`, `Order`, `Withdrawal`, `Treasury`) use `DECIMAL(36, 18)` or `DECIMAL(40, 18)` represented as strings in PHP.
- **NEVER** use native float arithmetic (`+`, `-`, `*`, `/`) for money. Always use `bcadd()`, `bcsub()`, `bccomp()`, `bcmul()`, and `bcdiv()`.
- Always perform ledger transactions via `WalletRepository::credit()`, `debit()`, or `transfer()`, which automatically guarantee balance verification and transactional atomicity.

---

## 🧪 5. Testing & Static Analysis Protocol

Before completing any database modification, you must execute:
1. `vendor/bin/phpunit` — verify all unit and functional tests pass.
2. `vendor/bin/phpstan analyse -c phpstan.neon` — verify Level 8 clean pass with 0 errors.
3. `vendor/bin/php-cs-fixer fix` — ensure PSR-12 and PHP 8.4 formatting.
4. `php bin/tueen-db tueen:db:validate` — confirm ORM metadata and database schema synchronization.
