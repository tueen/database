---
name: php-pdo-postgresql
description: >-
  Specialized skill for connecting to and interacting with PostgreSQL databases
  using PHP PDO (pdo_pgsql). Use when configuring Postgres DSNs, managing multi-tenant
  search_path schemas, using RETURNING clauses instead of sequences, querying JSON/JSONB
  columns, and handling Postgres-specific SQLSTATE errors.
---

# PHP PDO PostgreSQL Skill

This skill provides specialized guidelines, connection configuration, and best practices for developing with **PostgreSQL** using PHP Data Objects (`pdo_pgsql`).

---

## 1. Golden Rules for PostgreSQL with PDO

1. **Use `RETURNING` Clause Instead of `lastInsertId()`**:
   - In PostgreSQL, calling `$pdo->lastInsertId()` requires knowing the exact name of the underlying sequence (e.g. `users_id_seq`).
   - The modern and bulletproof approach is appending `RETURNING id` to your `INSERT` statements and calling `$stmt->fetchColumn()`.
2. **Strict Boolean Types**:
   - PostgreSQL has a strict native `BOOLEAN` type. Pass PHP booleans (`true`/`false`) with `PDO::PARAM_BOOL` or direct booleans in `execute()`. Passing strings like `'1'` or `'0'` can cause type casting friction.
3. **Multi-Tenant Schema Isolation via `search_path`**:
   - Utilize PostgreSQL's `search_path` to support multi-tenancy or separate schema namespaces without changing SQL queries.

---

## 2. Standard Connection Template

```php
<?php

declare(strict_types=1);

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = (int) (getenv('DB_PORT') ?: 5432);
$database = getenv('DB_DATABASE') ?: 'app_db';
$sslmode  = getenv('DB_SSLMODE') ?: 'prefer'; // disable, allow, prefer, require, verify-ca, verify-full

$dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode};options='--client_encoding=UTF8'";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'postgres', getenv('DB_PASSWORD') ?: '', $options);
} catch (PDOException $e) {
    error_log('PostgreSQL Connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed.');
}
```

---

## 3. The `RETURNING` Pattern for Inserts and Updates

```php
$stmt = $pdo->prepare('
    INSERT INTO orders (customer_id, total, status)
    VALUES (:customer_id, :total, :status)
    RETURNING id, created_at, status
');

$stmt->execute([
    'customer_id' => 45,
    'total'       => 129.50,
    'status'      => 'pending',
]);

$newOrder = $stmt->fetch();
$orderId   = (int) $newOrder['id'];
$createdAt = $newOrder['created_at'];
```

---

## 4. Native Upserts with `ON CONFLICT`

```php
$sql = '
    INSERT INTO user_preferences (user_id, theme, language)
    VALUES (:user_id, :theme, :language)
    ON CONFLICT (user_id) DO UPDATE SET
        theme    = EXCLUDED.theme,
        language = EXCLUDED.language,
        updated_at = NOW()
    RETURNING id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id'  => 10,
    'theme'    => 'dark',
    'language' => 'fa',
]);
```

---

## 5. JSON & JSONB Querying with PDO

PostgreSQL provides first-class JSON support. Use parameterized queries for JSON values:

```php
// Storing JSONB
$settings = ['email_notifications' => true, 'dark_mode' => false];

$stmt = $pdo->prepare('UPDATE users SET settings = :settings::jsonb WHERE id = :id');
$stmt->execute([
    'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
    'id'       => 10,
]);

// Querying inside JSONB using the ->> text extraction operator
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE settings->>'dark_mode' = :dark_mode");
$stmt->execute(['dark_mode' => 'false']);
$results = $stmt->fetchAll();
```

---

## 6. PostgreSQL Error Code Handling

PostgreSQL outputs standard ANSI SQLSTATE codes. Check `$e->getCode()` for specific error conditions:

```php
try {
    $stmt = $pdo->prepare('INSERT INTO users (email) VALUES (:email)');
    $stmt->execute(['email' => $email]);
} catch (PDOException $e) {
    switch ($e->getCode()) {
        case '23505': // unique_violation
            throw new InvalidArgumentException('Email address is already registered.');
        case '23503': // foreign_key_violation
            throw new InvalidArgumentException('Referenced record does not exist.');
        case '23502': // not_null_violation
            throw new InvalidArgumentException('A required field was omitted.');
        default:
            throw $e;
    }
}
```
