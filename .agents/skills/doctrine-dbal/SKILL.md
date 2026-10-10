---
name: doctrine-dbal
description: >-
  Expert guidance and best practices for Doctrine DBAL (Database Abstraction Layer).
  Use when connecting to databases via DriverManager, executing SQL queries, building
  queries with the DBAL QueryBuilder, managing transactions and savepoints, introspecting
  and modifying schemas with SchemaManager, and registering custom DBAL types.
---

# Doctrine DBAL Skill

This skill provides comprehensive instructions, patterns, and architectural rules for using **Doctrine DBAL** (Database Abstraction Layer) in modern PHP (PHP 8.1+), based on the [official Doctrine DBAL Documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/).

---

## 1. Core Principles & Modern DBAL Standards

1. **Use `DriverManager::getConnection()`**:
   - Never instantiate connection classes directly. Always use `DriverManager::getConnection($params, $config)`.
2. **Execute Queries with Explicit Intent**:
   - `executeQuery()` for `SELECT` queries returning rows (returns `Doctrine\DBAL\Result`).
   - `executeStatement()` for `INSERT`, `UPDATE`, `DELETE`, or DDL statements (returns `int` affected rows).
3. **Use Modern Result Fetching API**:
   - Never use deprecated methods like `$stmt->fetch()` or `$stmt->fetchAll()`.
   - Use `fetchAssociative()`, `fetchAllAssociative()`, `fetchOne()`, `fetchFirstColumn()`, or `iterateAssociative()`.
4. **Use Typed Parameter Binding**:
   - Bind parameters using `Doctrine\DBAL\ParameterType` or `Doctrine\DBAL\ArrayParameterType` (critical for `IN (:ids)` lists).
5. **Encapsulate Mutations in Transactions**:
   - Use `$conn->transactional(callable)` or explicit `beginTransaction()` / `commit()` / `rollBack()` blocks.

---

## 2. Establishing a Connection

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

$connectionParams = [
    'dbname'   => 'my_database',
    'user'     => 'db_user',
    'password' => 'secret',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'driver'   => 'pdo_mysql', // 'pdo_pgsql', 'pdo_sqlite', 'pdo_sqlsrv'
    'charset'  => 'utf8mb4',
];

$config = new Configuration();

// Establish connection
$conn = DriverManager::getConnection($connectionParams, $config);
```

### Supported Drivers in `connectionParams['driver']`:
- `pdo_mysql`: MySQL & MariaDB
- `pdo_pgsql`: PostgreSQL
- `pdo_sqlite`: SQLite 3 (`'path' => __DIR__ . '/app.db'` or `'memory' => true`)
- `pdo_sqlsrv`: Microsoft SQL Server
- `pdo_oci` / `oci8`: Oracle Database

---

## 3. Query Execution & Modern Result Fetching

```php
use Doctrine\DBAL\ParameterType;

// 1. Fetching a single scalar value (e.g. COUNT or single column)
$userCount = $conn->fetchOne(
    'SELECT COUNT(*) FROM users WHERE status = :status',
    ['status' => 'active'],
    ['status' => ParameterType::STRING]
);

// 2. Fetching a single row as associative array
$user = $conn->fetchAssociative(
    'SELECT id, name, email FROM users WHERE id = :id',
    ['id' => 42],
    ['id' => ParameterType::INTEGER]
);
// $user is array<string, mixed> or false if not found

// 3. Fetching all rows as array of associative arrays
$activeUsers = $conn->fetchAllAssociative(
    'SELECT id, name, email FROM users WHERE role = :role',
    ['role' => 'editor']
);

// 4. Fetching a single column as a flat array (list of IDs)
$userIds = $conn->fetchFirstColumn(
    'SELECT id FROM users WHERE status = :status',
    ['status' => 'banned']
);

// 5. Memory-efficient streaming of large datasets (Generator)
$result = $conn->executeQuery('SELECT id, payload FROM large_audit_logs');
foreach ($result->iterateAssociative() as $row) {
    processAuditRow($row);
}
```

---

## 4. Updates, Inserts & Deletes

```php
// Insert
$conn->insert('users', [
    'name'       => 'Alice',
    'email'      => 'alice@example.com',
    'created_at' => date('Y-m-d H:i:s'),
]);
$userId = (int) $conn->lastInsertId();

// Update
$affectedRows = $conn->update(
    'users',
    ['status' => 'suspended'],     // values to set
    ['id' => $userId]              // where criteria
);

// Delete
$conn->delete('sessions', ['user_id' => $userId]);

// Direct statement execution with parameters
$affected = $conn->executeStatement(
    'UPDATE accounts SET balance = balance - :amount WHERE id = :id AND balance >= :amount',
    ['amount' => 500, 'id' => 12],
    ['amount' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER]
);
```

---

## 5. The DBAL QueryBuilder

The DBAL QueryBuilder builds dynamic, SQL-injection safe SQL queries across database vendors.

```php
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

$qb = $conn->createQueryBuilder();

$qb->select('u.id', 'u.name', 'u.email', 'p.title AS profile_title')
   ->from('users', 'u')
   ->leftJoin('u', 'profiles', 'p', 'p.user_id = u.id')
   ->where('u.status = :status')
   ->andWhere('u.id IN (:ids)')
   ->setParameter('status', 'active', ParameterType::STRING)
   ->setParameter('ids', [1, 2, 5, 8], ArrayParameterType::INTEGER)
   ->orderBy('u.created_at', 'DESC')
   ->setMaxResults(20)
   ->setFirstResult(0);

$users = $qb->fetchAllAssociative();
```

> [!IMPORTANT]
> When querying with `IN (:list)`, always use `ArrayParameterType::INTEGER`, `ArrayParameterType::STRING`, or `ArrayParameterType::ASCII`. DBAL will automatically expand the placeholder into the correct number of positional or named markers.

---

## 6. Transactions & Savepoints

### Idiomatic Functional Transaction (`transactional`)

Automatically commits on successful return and rolls back if any `Throwable` is thrown:

```php
$conn->transactional(function ($connection) use ($sourceId, $targetId, $amount): void {
    $connection->executeStatement(
        'UPDATE accounts SET balance = balance - :amount WHERE id = :id',
        ['amount' => $amount, 'id' => $sourceId]
    );

    $connection->executeStatement(
        'UPDATE accounts SET balance = balance + :amount WHERE id = :id',
        ['amount' => $amount, 'id' => $targetId]
    );
});
```

### Manual Transaction with Savepoint Support

```php
// Enable savepoints for nested transaction emulation
$conn->setNestTransactionsWithSavepoints(true);

$conn->beginTransaction();
try {
    // Operations...
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}
```

---

## 7. Schema Manager & Programmatic DDL

Inspect or create database structures programmatically:

```php
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

$schemaManager = $conn->createSchemaManager();

// Introspect existing tables
$tableNames = $schemaManager->listTableNames();

// Create a new table programmatically
$table = new Table('products');
$table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
$table->addColumn('sku', Types::STRING, ['length' => 64]);
$table->addColumn('price', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
$table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
$table->setPrimaryKey(['id']);
$table->addUniqueIndex(['sku']);

$schemaManager->createTable($table);
```

---

## 8. Custom Types Registration

Doctrine DBAL supports mapping database columns to custom PHP objects:

```php
use Doctrine\DBAL\Types\Type;

// Register custom type if not registered
if (!Type::hasType('money')) {
    Type::addType('money', App\DBAL\Types\MoneyType::class);
}

// Associate with database platform type if needed
$conn->getDatabasePlatform()->registerDoctrineTypeMapping('money', 'money');
```

---

## 9. References & Examples Navigation

- **Connection & Drivers Guide**: [references/connection.md](./references/connection.md) - Deep dive on DSNs, pooled connections, and platform overrides.
- **QueryBuilder In-Depth**: [references/query_builder.md](./references/query_builder.md) - Complex joins, subqueries, unions, pagination, and expression builder.
- **Schema Management**: [references/schema_manager.md](./references/schema_manager.md) - Introspection, comparator, migration diffing, and programmatic table alterations.
- **Production Code Example**: [examples/dbal_usage.php](./examples/dbal_usage.php) - Fully tested, runnable implementation of connection, CRUD, batch insert, and streaming.
