# MySQL & MariaDB with PDO (`pdo_mysql`)

Comprehensive guide for configuring, optimizing, and securing MySQL and MariaDB databases using PHP Data Objects.

---

## 1. DSN Specification & Mandatory Charset

```php
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=my_database;charset=utf8mb4';
```

### Critical Rules:

1. **Always use `charset=utf8mb4` in DSN**:
   - `utf8` in MySQL only supports up to 3-byte characters, breaking modern emojis and 4-byte UTF-8 scripts.
   - Setting `charset` in the DSN configures the client character set at the handshake level.
   - **Never** substitute this with `$pdo->query("SET NAMES 'utf8mb4'")`. Doing so leaves the connection vulnerable to multibyte character injection attacks if emulation is active.

2. **Connecting via Unix Socket**:
   - On Linux systems where PHP and MySQL are on the same machine, Unix domain sockets avoid TCP/IP loopback overhead:
   ```php
   $dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=my_database;charset=utf8mb4';
   ```

---

## 2. Production PDO Options for MySQL

```php
$options = [
    // Throw PDOException on all errors
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // Return rows as associative arrays
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // CRITICAL: Disable emulated prepared statements
    // This sends actual prepared statements to MySQL via mysqlnd
    PDO::ATTR_EMULATE_PREPARES => false,

    // Prevent integers and floats from being converted to strings
    PDO::ATTR_STRINGIFY_FETCHES => false,

    // Enable buffered queries for standard workloads (default)
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,

    // Connect timeout in seconds
    PDO::ATTR_TIMEOUT => 5,
];
```

---

## 3. Buffered vs. Unbuffered Queries

### When to Use Buffered Queries (Default: `true`)
MySQL buffers the entire query result set in PHP memory before execution returns.
- **Pros**: You can use `$stmt->rowCount()`, move back/forth, and run another query on the same connection while holding the statement.
- **Cons**: High memory consumption on massive result sets (millions of rows).

### When to Use Unbuffered Queries (`false`)
Unbuffered queries fetch rows on-demand from the MySQL server as you iterate.
- **Pros**: Minimal PHP memory footprint, regardless of row count.
- **Cons**: Cannot execute other queries on the same PDO connection until the result set is exhausted or closed via `$stmt->closeCursor()`.

```php
// Creating an unbuffered statement for large batch exports:
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

$stmt = $pdo->query('SELECT id, payload, created_at FROM large_event_log');
while ($row = $stmt->fetch()) {
    exportToCsv($row);
}
$stmt->closeCursor();

// Re-enable buffered queries for regular operations
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
```

---

## 4. Secure SSL / TLS Connection

To encrypt data in transit between PHP and MySQL:

```php
$options = [
    PDO::MYSQL_ATTR_SSL_CA                 => '/etc/ssl/certs/mysql-ca.pem',
    PDO::MYSQL_ATTR_SSL_CERT               => '/etc/ssl/certs/client-cert.pem',
    PDO::MYSQL_ATTR_SSL_KEY                => '/etc/ssl/certs/client-key.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

$pdo = new PDO($dsn, $user, $password, $options);
```

---

## 5. Insert, Last Insert ID & Upserts

### Last Insert ID
```php
$stmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
$stmt->execute([
    'name'  => 'Alice',
    'email' => 'alice@example.com',
]);

$userId = (int) $pdo->lastInsertId();
```

### Safe Upsert (`INSERT ... ON DUPLICATE KEY UPDATE`)
In MySQL 8.0.19+, use the standard alias or `VALUES()`:

```php
$sql = '
    INSERT INTO user_stats (user_id, views, last_seen)
    VALUES (:user_id, :views, NOW())
    ON DUPLICATE KEY UPDATE
        views = views + VALUES(views),
        last_seen = VALUES(last_seen)
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id' => $userId,
    'views'   => 1,
]);
```

---

## 6. Deadlock Handling & Automatic Retries

MySQL InnoDB uses row-level locking and can throw deadlock errors (`SQLSTATE 40001` or MySQL error code `1213`). Implement retry logic for critical transactions:

```php
function executeWithDeadlockRetry(PDO $pdo, callable $callback, int $maxRetries = 3): mixed
{
    $attempts = 0;
    while (true) {
        $attempts++;
        try {
            $pdo->beginTransaction();
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 40001 is SQLSTATE for serialization failure / deadlock
            // 1213 is MySQL error code for deadlock
            $isDeadlock = ($e->getCode() === '40001' || ($e->errorInfo[1] ?? 0) === 1213);
            if ($isDeadlock && $attempts < $maxRetries) {
                usleep(50000 * $attempts); // 50ms, 100ms backoff
                continue;
            }
            throw $e;
        }
    }
}
```
