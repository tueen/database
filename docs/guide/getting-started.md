# Getting Started with Tueen Database

Welcome to **Tueen Database**, the modern, zero-config relational database component tailored specifically for high-throughput Telegram bots.

---

## 📦 Requirements

- **PHP 8.4 or higher** (Supports PHP 8.4, 8.5+)
- **PDO Extension** (`pdo_sqlite`, `pdo_mysql`, or `pdo_pgsql`)
- **BCMath Extension** (for arbitrary-precision currency math)
- **Composer 2.x**

---

## 🚀 Installation

Install the package via Composer into your Telegram bot project:

```bash
composer require tueen/database
```

---

## ⚡ Quick Initialization

Tueen Database operates with zero configuration out of the box. By default, it automatically manages schema synchronization without requiring you to manually run migration scripts.

### 1. Zero-Config In-Memory Database (Ideal for Tests & Prototyping)

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

$db = DatabaseManager::create(
    DatabaseConfig::createSqlite(':memory:')
);
```

### 2. File-Based SQLite Database (Small to Medium Bots)

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

$db = DatabaseManager::create(
    DatabaseConfig::createSqlite(__DIR__ . '/var/bot.sqlite')
);
```

### 3. Production MySQL / MariaDB (High Concurrency Bots)

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
    )
);
```

---

## 💬 Basic Usage Example

Here is how you handle user interactions and ledger operations in your bot's webhook or polling loop:

```php
use Tueen\Database\Enum\ChatType;

// Fetch or register a user when they send /start
$chat = $db->chats()->findOrCreate(
    chatId: $update['message']['chat']['id'],
    username: $update['message']['chat']['username'] ?? null,
    name: $update['message']['chat']['first_name'] ?? 'User',
    type: ChatType::Private,
    languageCode: $update['message']['from']['language_code'] ?? 'en',
);

// Greet the user using PHP 8.4 property hooks
echo "Welcome back, {$chat->displayName}!\n";

// Credit 50 Stars or Points to their multi-currency wallet
$tx = $db->wallets()->credit(
    chat: $chat,
    amount: '50.000000000000000000',
    currency: 'STARS',
    source: 'daily_reward',
);

echo "New STARS Balance: " . $db->wallets()->getBalance($chat, 'STARS') . "\n";
```

---

## 🧭 Next Steps

- Explore the **[Architecture & Engine Overview](/guide/architecture)** to understand the internal design.
- Learn how **[Zero-Config Auto-Migrations](/guide/zero-config-migrations)** work under the hood.
- Review the complete **[Entity Models Catalog](/reference/entities)**.
