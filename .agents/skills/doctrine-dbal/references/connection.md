# Doctrine DBAL Connection & Configuration Reference

This guide covers advanced connection parameters, middleware, platform overrides, and events for `Doctrine\DBAL\Connection`.

---

## 1. Connection Parameters by Driver

### MySQL / MariaDB (`pdo_mysql`)
```php
$params = [
    'driver'        => 'pdo_mysql',
    'host'          => '127.0.0.1',
    'port'          => 3306,
    'dbname'        => 'app_db',
    'user'          => 'db_user',
    'password'      => 'secret',
    'charset'       => 'utf8mb4',
    'driverOptions' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_SSL_CA     => '/path/to/ca.pem',
    ],
];
```

### PostgreSQL (`pdo_pgsql`)
```php
$params = [
    'driver'        => 'pdo_pgsql',
    'host'          => '127.0.0.1',
    'port'          => 5432,
    'dbname'        => 'app_db',
    'user'          => 'postgres',
    'password'      => 'secret',
    'sslmode'       => 'prefer',
    'driverOptions' => [
        // search_path or custom options
    ],
];
```

### SQLite (`pdo_sqlite`)
```php
// File-based
$params = [
    'driver' => 'pdo_sqlite',
    'path'   => __DIR__ . '/var/data.db',
];

// In-Memory
$params = [
    'driver' => 'pdo_sqlite',
    'memory' => true,
];
```

### URL / DSN Connection String
DBAL also supports full URLs via the `url` parameter:
```php
$params = [
    'url' => 'mysql://user:pass@127.0.0.1:3306/app_db?charset=utf8mb4',
];
```

---

## 2. DBAL Middleware & Logging

In DBAL 3 and 4, logging and profiling are configured via Middleware instead of deprecated SQLLogger implementations:

```php
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Psr\Log\LoggerInterface;

$config = new Configuration();

// Attach PSR-3 compatible logger middleware
if ($logger instanceof LoggerInterface) {
    $config->setMiddlewares([
        new LoggingMiddleware($logger),
    ]);
}
```

---

## 3. Platform Detection and Overrides

Normally DBAL auto-detects the server version and database platform upon the first query. To prevent extra discovery queries on every request, supply the explicit server version in your connection parameters:

```php
$params = [
    'driver'         => 'pdo_mysql',
    'serverVersion'  => '8.0.32', // prevents version detection query
    // ...
];
```
