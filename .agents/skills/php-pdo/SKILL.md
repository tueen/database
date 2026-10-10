---
name: php-pdo
description: >-
  Expert guidance and best practices for working with PHP Data Objects (PDO).
  Use when connecting to databases, preparing and executing SQL statements,
  managing transactions, configuring driver options (MySQL, PostgreSQL, SQLite,
  SQL Server, Oracle), and securing PHP database code against SQL injection.
---

# PHP Data Objects (PDO) Master Skill

This skill provides comprehensive instructions, standards, and production-tested patterns for using **PHP Data Objects (PDO)** in modern PHP (PHP 8.0+), grounded in the [official PHP PDO Manual](https://www.php.net/manual/en/book.pdo.php) and [PHP: The Right Way](https://phptherightway.com/#pdo_extension).

---

## 1. Core Principles & Non-Negotiable Standards

1. **Always Use Prepared Statements for Untrusted Data**:
   - Never concatenate or interpolate user input (`$_GET`, `$_POST`, request bodies) directly into SQL strings.
   - Use parameter markers (`?` positional or `:name` named).
2. **Always Enable Exception Error Mode**:
   - Set `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`. While this is the default since PHP 8.0, explicitly defining it guarantees consistent behavior across environments.
3. **Always Disable Prepared Statement Emulation**:
   - Set `PDO::ATTR_EMULATE_PREPARES => false`. This forces native prepared statements on database engines that support them (MySQL, PostgreSQL, SQLite), ensures real separation between query plan and user data, and delivers typed values (integers as PHP `int`, floats as `float`).
4. **Set Meaningful Fetch Defaults**:
   - Use `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` to retrieve clean associative arrays without duplicate numeric indexes.
5. **Set Proper Character Encoding in DSN**:
   - Always declare charset in the DSN (e.g. `charset=utf8mb4` for MySQL). Never execute `SET NAMES` queries manually after connection, as that leaves multibyte SQL injection vectors open.

---

## 2. Universal Connection Pattern

### Recommended Connection Configuration

```php
<?php

declare(strict_types=1);

$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=my_app;charset=utf8mb4';
$user = getenv('DB_USER') ?: 'app_user';
$pass = getenv('DB_PASS') ?: 'secret_password';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log message securely, DO NOT display raw exception/passwords to end users
    error_log('Database connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database service temporarily unavailable.');
}
```

---

## 3. Prepared Statements & Parameter Binding

### Positional vs. Named Parameters

Never mix positional (`?`) and named (`:param`) parameters within the same query.

#### Example A: Passing Values to `execute()` (Recommended for simplicity)

```php
// Named placeholders
$stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE status = :status AND role = :role');
$stmt->execute([
    'status' => 'active',
    'role'   => 'admin',
]);
$users = $stmt->fetchAll();

// Positional placeholders
$stmt = $pdo->prepare('UPDATE products SET price = ?, stock = ? WHERE id = ?');
$stmt->execute([19.99, 150, 42]);
```

#### Example B: Explicit Typing with `bindValue()`

Use `bindValue()` when specifying parameter types or binding specific literals:

```php
$stmt = $pdo->prepare('SELECT * FROM items LIMIT :limit OFFSET :offset');
$stmt->bindValue(':limit', 10, PDO::PARAM_INT);
$stmt->bindValue(':offset', 20, PDO::PARAM_INT);
$stmt->execute();
```

> [!WARNING]
> `bindParam()` binds by reference (evaluating the variable when `execute()` is called), whereas `bindValue()` binds the value immediately. In 95% of use cases, prefer `bindValue()` or passing values directly into `execute()`.

---

## 4. Querying & Fetch Modes

| Fetch Mode | Description | Example Usage |
| :--- | :--- | :--- |
| `PDO::FETCH_ASSOC` | Column name indexed array | `$stmt->fetchAll(PDO::FETCH_ASSOC)` |
| `PDO::FETCH_COLUMN` | Single 0-indexed column | `$stmt->fetchAll(PDO::FETCH_COLUMN, 0)` |
| `PDO::FETCH_KEY_PAIR` | First column as key, second as value | `$stmt->fetchAll(PDO::FETCH_KEY_PAIR)` |
| `PDO::FETCH_CLASS` | Maps columns directly to object properties | `$stmt->fetchAll(PDO::FETCH_CLASS, User::class)` |
| `PDO::FETCH_OBJ` | Anonymous object with column names as properties | `$stmt->fetch(PDO::FETCH_OBJ)` |
| `PDO::FETCH_UNIQUE` | First column used as unique array key | `$stmt->fetchAll(PDO::FETCH_UNIQUE \| PDO::FETCH_ASSOC)` |
| `PDO::FETCH_GROUP` | Groups rows by first column | `$stmt->fetchAll(PDO::FETCH_GROUP \| PDO::FETCH_ASSOC)` |

### Memory-Efficient Streaming for Large Datasets

Do not call `fetchAll()` when querying tens of thousands of rows. Iterate over the statement directly:

```php
$stmt = $pdo->query('SELECT id, email, created_at FROM large_audit_log');

// Statement is directly iterable via Traversable (PHP 8+)
foreach ($stmt as $row) {
    processRow($row);
}
```

---

## 5. Safe Dynamic SQL (Identifiers & IN Clauses)

### Dynamic Identifiers (Table Names, Column Names, Sort Direction)

Placeholders (`?`, `:param`) **cannot** be used for column names, table names, or `ASC`/`DESC`. You must strictly whitelist them:

```php
$allowedSortColumns = ['name', 'price', 'created_at'];
$allowedOrders = ['ASC', 'DESC'];

$sort = in_array($_GET['sort'] ?? '', $allowedSortColumns, true) ? $_GET['sort'] : 'created_at';
$order = (strtoupper($_GET['order'] ?? '') === 'DESC') ? 'DESC' : 'ASC';

// Safe to interpolate after strict whitelisting
$sql = "SELECT id, name, price FROM products ORDER BY {$sort} {$order}";
$stmt = $pdo->query($sql);
```

### Dynamic `IN (...)` Clauses

Dynamically construct placeholders matching the input array count:

```php
$ids = [3, 7, 12, 45];

if (empty($ids)) {
    $results = [];
} else {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id IN ($placeholders) AND status = ?");
    $stmt->execute([...$ids, 'active']);
    $results = $stmt->fetchAll();
}
```

---

## 6. Transactions & Atomicity

Always encapsulate multi-step mutations within transactions. Verify `inTransaction()` before rolling back to prevent errors when exceptions happen before transaction initiation.

```php
try {
    $pdo->beginTransaction();

    $stmt1 = $pdo->prepare('UPDATE accounts SET balance = balance - :amount WHERE id = :from');
    $stmt1->execute(['amount' => 500, 'from' => $sourceAccountId]);

    $stmt2 = $pdo->prepare('UPDATE accounts SET balance = balance + :amount WHERE id = :to');
    $stmt2->execute(['amount' => 500, 'to' => $targetAccountId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Transfer failed: ' . $e->getMessage());
    throw $e;
}
```

---

## 7. Supported Database Drivers Overview

PDO supports multiple drivers through its unified API. Check installed drivers with `PDO::getAvailableDrivers()`.

| Driver | Database | DSN Prefix | Detailed Guide |
| :--- | :--- | :--- | :--- |
| `pdo_mysql` | MySQL & MariaDB | `mysql:` | [MySQL Guide](./references/mysql.md) or [`php-pdo-mysql`](../php-pdo-mysql/SKILL.md) |
| `pdo_pgsql` | PostgreSQL | `pgsql:` | [PostgreSQL Guide](./references/postgresql.md) or [`php-pdo-postgresql`](../php-pdo-postgresql/SKILL.md) |
| `pdo_sqlite` | SQLite 3 | `sqlite:` | [SQLite Guide](./references/sqlite.md) or [`php-pdo-sqlite`](../php-pdo-sqlite/SKILL.md) |
| `pdo_sqlsrv` / `pdo_dblib` | Microsoft SQL Server | `sqlsrv:` / `dblib:` | [Drivers Reference](./references/drivers.md#microsoft-sql-server) |
| `pdo_oci` | Oracle Database | `oci:` | [Drivers Reference](./references/drivers.md#oracle-database) |
| `pdo_odbc` | ODBC Compatible DBs | `odbc:` | [Drivers Reference](./references/drivers.md#odbc) |

---

## 8. Directory & Resource Navigation

- **Reusable Connection Factory**: [examples/Database.php](./examples/Database.php) - Production-grade connection manager with driver-specific configurations.
- **CRUD & Transaction Patterns**: [examples/CrudExamples.php](./examples/CrudExamples.php) - Complete query, insert, update, delete, and streaming examples.
- **Dynamic Queries & Whitelisting**: [examples/DynamicQueries.php](./examples/DynamicQueries.php) - Handling variable columns, IN filters, and bulk inserts.
- **Driver Diagnostics Script**: [scripts/check_pdo.php](./scripts/check_pdo.php) - CLI script to check active drivers and test database connectivity.
- **Security Guide**: [references/security.md](./references/security.md) - SQL injection mitigation, UTF-8 nuances, identifier escaping, and credential management.
