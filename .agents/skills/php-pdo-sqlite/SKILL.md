---
name: php-pdo-sqlite
description: >-
  Specialized skill for connecting to and optimizing SQLite databases using
  PHP PDO (pdo_sqlite). Use when configuring SQLite file or in-memory databases,
  enabling mandatory PRAGMAs (foreign_keys, WAL mode, busy_timeout), registering
  custom PHP functions in SQL (sqliteCreateFunction), and handling concurrency locks.
---

# PHP PDO SQLite Skill

This skill provides specialized guidelines, connection configuration, and best practices for developing with **SQLite 3** using PHP Data Objects (`pdo_sqlite`).

---

## 1. Golden Rules for SQLite with PDO

1. **Foreign Keys are OFF by Default**:
   - SQLite disables foreign key enforcement by default for backward compatibility. You **must** execute `PRAGMA foreign_keys = ON;` immediately after connecting.
2. **Enable WAL (Write-Ahead Logging) Mode**:
   - Standard journal mode locks the entire database file during writes. `PRAGMA journal_mode = WAL;` allows simultaneous readers and writers.
3. **Configure `busy_timeout`**:
   - Set `PRAGMA busy_timeout = 5000;` so PHP waits up to 5 seconds for write locks to clear instead of throwing immediate `database is locked` errors.
4. **Use `BEGIN IMMEDIATE` for Write Transactions**:
   - Default deferred transactions can cause deadlocks if multiple processes read then decide to write at the same time.

---

## 2. Standard Connection Template & Initialization

```php
<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../storage/database.sqlite';

// Ensure the storage directory exists and has write permissions
$dir = dirname($databasePath);
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$dsn = 'sqlite:' . $databasePath;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, null, null, $options);

    // Mandatory Production PRAGMAs:
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');
} catch (PDOException $e) {
    error_log('SQLite connection error: ' . $e->getMessage());
    throw new RuntimeException('Unable to open database.');
}
```

---

## 3. Registering Custom PHP Functions in SQL Queries

The `pdo_sqlite` driver enables registering PHP functions as native SQL functions and aggregates:

### Custom Scalar Function

```php
// Register a custom slugify function
$pdo->sqliteCreateFunction('slugify', function (string $str): string {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str), '-'));
}, 1);

// Use it directly in SQL:
$stmt = $pdo->prepare('INSERT INTO posts (title, slug) VALUES (:title, slugify(:title))');
$stmt->execute(['title' => 'My First Blog Post']);
```

### Custom Aggregate Function

```php
// Register custom multiplier aggregate
$pdo->sqliteCreateAggregate(
    'product',
    function (&$total, int $step, int $val): void {
        $total = ($total === null) ? $val : ($total * $val);
    },
    function (&$total): ?int {
        return $total;
    },
    1
);

$res = $pdo->query('SELECT product(factor) AS combined FROM multipliers')->fetch();
```

---

## 4. Concurrency & Write Transactions

To prevent `database is locked` race conditions in concurrent web traffic:

```php
// Begin immediate transaction to acquire the write lock upfront
$pdo->exec('BEGIN IMMEDIATE TRANSACTION');

try {
    $stmt = $pdo->prepare('UPDATE inventory SET stock = stock - 1 WHERE item_id = :id AND stock > 0');
    $stmt->execute(['id' => $itemId]);

    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Item out of stock.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
```

---

## 5. In-Memory Database for Fast Unit Testing

In-memory databases exist entirely in RAM and are destroyed as soon as the connection closes, making them ideal for PHPUnit tests:

```php
$testPdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

$testPdo->exec('PRAGMA foreign_keys = ON;');
$testPdo->exec('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    );
');
```
