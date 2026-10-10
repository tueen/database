---
layout: home

hero:
  name: "Tueen Database"
  text: "Relational Engine for Telegram Bots"
  tagline: "The zero-config, high-throughput database engine engineered for high-concurrency bots on PHP 8.4+ and Doctrine ORM 3."
  image:
    src: /logo.svg
    alt: Tueen Database Logo
  actions:
    - theme: brand
      text: 🚀 Get Started
      link: /guide/getting-started
    - theme: alt
      text: 🏛️ Architecture Overview
      link: /guide/architecture
    - theme: alt
      text: 📖 API Reference
      link: /reference/entities

features:
  - icon: ⚡
    title: Declarative Zero-Config
    details: Instant lazy schema synchronization on boot. No manual migrations needed to launch bots or install extensions.
  - icon: 🪝
    title: PHP 8.4 Modern Architecture
    details: Utilizes native property hooks, asymmetric visibility, native lazy objects, and backed enums for rock-solid type safety.
  - icon: 💬
    title: Telegram-Native Domain
    details: Purpose-built for 64-bit signed chat IDs (private, supergroups, channels), automatic username normalization, and UTF-8 multi-byte emojis.
  - icon: 💳
    title: Arbitrary-Precision Ledger
    details: Multi-currency wallet balances (TON, Stars, USDT, fiat) with audited double-entry transactions and atomic balance protection.
  - icon: 🎯
    title: Broadcast Campaign Engine
    details: Chunked mass messaging pipeline with chat filtering, offset cursor tracking, and real-time completion telemetry.
  - icon: 🌐
    title: Multi-Database Portability
    details: "Runs identically on zero-dependency SQLite (:memory: & file), MySQL 8+, MariaDB, and PostgreSQL."
---

## ⚡ Instant Code Example

Initialize your database in seconds with full transactional safety:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

// 1. Boot engine with Zero-Config SQLite or MySQL
$db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));

// 2. Register or fetch a Telegram user (automatic @ stripping & emoji safety)
$chat = $db->chats()->findOrCreate(
    chatId: 123456789,
    username: '@super_user',
    name: 'Alice 🚀',
);

// 3. Perform an atomic multi-currency credit with audit transaction
$tx = $db->wallets()->credit(
    chat: $chat,
    amount: '100.000000000000000000',
    currency: 'TON',
    source: 'stars_deposit',
);

// 4. Check real-time property hook states
if ($chat->isPrivate && $chat->canReceiveMessages) {
    // Deliver bot message
}
```
