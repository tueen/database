# SQLite with PDO (`pdo_sqlite`)

Comprehensive guide for configuring, optimizing, and utilizing SQLite 3 databases using PHP Data Objects.

---

## 1. DSN Formats & Storage Modes

```php
// 1. File Database (Absolute Path)
$dsn = 'sqlite:' . __DIR__ . '/../storage/app.db';

// 2. File Database (Relative Path)
$dsn = 'sqlite:data/database.sqlite';

// 3. In-Memory Database (Volatile, perfect for fast unit testing)
$dsn = 'sqlite::memory:';

// 4. Temporary Database (Kept in disk temp files until connection closes)
$dsn = 'sqlite:';
```

---

## 2. Essential Production PRAGMAs

> [!IMPORTANT]
> By default, SQLite has foreign key constraints disabled and operates in rollback journal mode, which causes frequent `database is locked` errors during concurrent web requests. Always run these PRAGMAs right after establishing a connection.

```php
$pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// 1. Mandatory: Turn ON foreign key constraint enforcement
$pdo->exec('PRAGMA foreign_keys = ON;');

// 2. High-Performance Concurrency: Write-Ahead Logging (WAL)
// Readers do not block writers, and writers do not block readers.
$pdo->exec('PRAGMA journal_mode = WAL;');

// 3. Concurrency Protection: Wait up to 5000ms for locks to clear before failing
$pdo->exec('PRAGMA busy_timeout = 5000;');

// 4. Balanced Safety & Speed: Synchronous Normal (ideal with WAL)
$pdo->exec('PRAGMA synchronous = NORMAL;');
```

---

## 3. Registering Custom PHP Functions in SQLite

The `pdo_sqlite` driver provides native methods to register custom PHP functions and aggregates directly into the SQLite SQL engine:

### Custom Scalar Function: `sqliteCreateFunction`

```php
// Register a PHP function to compute slug inside SQL queries
$pdo->sqliteCreateFunction('slugify', function (string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    return strtolower($text);
}, 1);

// Now callable inside SQL queries:
$stmt = $pdo->prepare('UPDATE articles SET slug = slugify(title) WHERE id = :id');
$stmt->execute(['id' => 10]);
```

### Custom Aggregate Function: `sqliteCreateAggregate`

```php
// Register custom concatenation aggregate
$pdo->sqliteCreateAggregate(
    'group_pipe',
    function (&$context, int $row, string $string): void {
        $context = ($context === null) ? $string : ($context . ' | ' . $string);
    },
    function (&$context): ?string {
        return $context;
    },
    1
);

$res = $pdo->query('SELECT group_pipe(name) AS all_names FROM categories')->fetch();
```

---

## 4. SQLite Transactions & Lock Handling

By default, `$pdo->beginTransaction()` initiates a `DEFERRED` transaction. If multiple concurrent requests begin deferred transactions and both try to write, one will fail with `database is locked`.

For write-heavy operations under concurrency, initiate an `IMMEDIATE` transaction:

```php
// Reserves the write lock immediately, preventing concurrent write clashes
$pdo->exec('BEGIN IMMEDIATE TRANSACTION');

try {
    $stmt = $pdo->prepare('INSERT INTO orders (user_id, total) VALUES (:uid, :total)');
    $stmt->execute(['uid' => 1, 'total' => 99.95]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
```

---

## 5. SQLite Upserts (`ON CONFLICT`)

Since SQLite 3.24.0, native upsert is fully supported:

```php
$sql = '
    INSERT INTO site_metrics (metric_name, metric_value, updated_at)
    VALUES (:name, :val, CURRENT_TIMESTAMP)
    ON CONFLICT (metric_name) DO UPDATE SET
        metric_value = metric_value + EXCLUDED.metric_value,
        updated_at = CURRENT_TIMESTAMP
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'name' => 'page_views',
    'val'  => 1,
]);
```
