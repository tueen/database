---
name: php-pdo-mysql
description: >-
  Specialized skill for connecting to and interacting with MySQL and MariaDB databases
  using PHP PDO (pdo_mysql). Use when configuring MySQL DSNs, handling utf8mb4 encoding,
  configuring unbuffered vs buffered queries, using native prepared statements with mysqlnd,
  handling deadlocks, and setting up SSL/TLS.
---

# PHP PDO MySQL & MariaDB Skill

This skill provides specialized guidelines, connection templates, and best practices for developing with **MySQL** and **MariaDB** using PHP Data Objects (`pdo_mysql`).

---

## 1. Golden Rules for MySQL with PDO

1. **Mandatory UTF-8 Charset in DSN**:
   - Always specify `charset=utf8mb4` in the DSN. Never use `utf8` (which only supports 3 bytes).
   - Never use `SET NAMES 'utf8mb4'` query manually after connecting, as it leaves multi-byte SQL injection vulnerabilities open when prepared statement emulation is enabled.
2. **Always Turn Off Prepared Statement Emulation**:
   - Set `PDO::ATTR_EMULATE_PREPARES => false`. This instructs `mysqlnd` to execute true binary prepared statements on the MySQL server, preserving native types (`int` stays `int`, `float` stays `float`) and shielding against multi-query injection.
3. **Use Unix Sockets on Local Linux Servers**:
   - Prefer `unix_socket=/var/run/mysqld/mysqld.sock` over `host=127.0.0.1` for local connections to bypass the TCP loopback stack.

---

## 2. Standard Connection Template

```php
<?php

declare(strict_types=1);

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = (int) (getenv('DB_PORT') ?: 3306);
$database = getenv('DB_DATABASE') ?: 'app_db';
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES         => false,
    PDO::ATTR_STRINGIFY_FETCHES        => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::ATTR_TIMEOUT                  => 5,
];

try {
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', $options);
} catch (PDOException $e) {
    error_log('MySQL Connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed.');
}
```

---

## 3. Large Dataset Streaming (Unbuffered Queries)

By default, MySQL buffers all results into PHP memory. For queries returning millions of rows, switch to unbuffered mode to prevent Out-Of-Memory (OOM) fatal errors:

```php
// Switch to unbuffered mode
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

$stmt = $pdo->query('SELECT id, event_type, payload FROM activity_logs');

// Read row by row from the server stream
while ($row = $stmt->fetch()) {
    processEvent($row);
}

// Close cursor before executing any other statement on the same connection
$stmt->closeCursor();

// Re-enable buffered queries for regular app queries
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
```

---

## 4. Upserts: `INSERT ... ON DUPLICATE KEY UPDATE`

In MySQL 8.0.19+, use the standard alias or `VALUES()` syntax:

```php
$sql = '
    INSERT INTO user_login_counts (user_id, login_count, last_login)
    VALUES (:user_id, 1, NOW())
    ON DUPLICATE KEY UPDATE
        login_count = login_count + 1,
        last_login  = NOW()
';

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => 101]);
```

---

## 5. Deadlock Retry Handler (InnoDB)

High-concurrency workloads on InnoDB can encounter deadlocks (`SQLSTATE 40001` / Error `1213`). Encapsulate transactions with automatic exponential backoff:

```php
function transactionWithRetry(PDO $pdo, callable $callback, int $maxAttempts = 3): mixed
{
    $attempt = 0;
    while (true) {
        $attempt++;
        try {
            $pdo->beginTransaction();
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $isDeadlock = ($e->getCode() === '40001' || ($e->errorInfo[1] ?? 0) === 1213);
            if ($isDeadlock && $attempt < $maxAttempts) {
                usleep(50000 * $attempt); // wait 50ms, 100ms
                continue;
            }

            throw $e;
        }
    }
}
```

---

## 6. Secure SSL / TLS Configuration

For remote databases (e.g. AWS RDS, DigitalOcean Managed MySQL, PlanetScale):

```php
$sslOptions = [
    PDO::MYSQL_ATTR_SSL_CA                 => '/etc/ssl/certs/ca-certificates.crt',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

$pdo = new PDO($dsn, $user, $pass, $options + $sslOptions);
```
