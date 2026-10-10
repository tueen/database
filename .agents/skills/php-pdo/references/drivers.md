# Complete PDO Drivers Reference

This reference documents the connection strings (DSN), prerequisites, and options for all drivers supported by PHP PDO.

To verify available drivers at runtime, run:
```php
print_r(PDO::getAvailableDrivers());
```

---

## 1. MySQL & MariaDB (`pdo_mysql`)

- **Extension**: `extension=pdo_mysql`
- **Underlying Client**: mysqlnd (recommended) or libmysqlclient

### DSN Formats

```php
// TCP Connection with Port
$dsn = 'mysql:host=127.0.0.1;port=3306;dbname=my_database;charset=utf8mb4';

// Unix Domain Socket (faster on Linux)
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=my_database;charset=utf8mb4';
```

### Driver-Specific Attributes

```php
$options = [
    // Ensure native prepared statements
    PDO::ATTR_EMULATE_PREPARES => false,
    
    // Unbuffered queries (useful when reading huge tables without OOM)
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // default: true
    
    // SSL Configuration
    PDO::MYSQL_ATTR_SSL_CA   => '/path/to/ca-cert.pem',
    PDO::MYSQL_ATTR_SSL_CERT => '/path/to/client-cert.pem',
    PDO::MYSQL_ATTR_SSL_KEY  => '/path/to/client-key.pem',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    
    // Connection timeout (in seconds)
    PDO::ATTR_TIMEOUT => 5,
];
```

---

## 2. PostgreSQL (`pdo_pgsql`)

- **Extension**: `extension=pdo_pgsql`
- **Underlying Client**: libpq

### DSN Formats

```php
// Standard TCP connection
$dsn = 'pgsql:host=localhost;port=5432;dbname=my_database;sslmode=prefer';

// Specifying schema search path in options
$dsn = "pgsql:host=localhost;port=5432;dbname=my_database;options='--client_encoding=UTF8 -c search_path=tenant_a,public'";
```

### Key Differences from MySQL

1. **Auto-increment & Last Insert ID**:
   - `lastInsertId(?string $sequenceName = null)` requires the name of the sequence in PostgreSQL (e.g. `$pdo->lastInsertId('users_id_seq')`).
   - Prefer modern SQL `INSERT INTO users (...) VALUES (...) RETURNING id` and call `$stmt->fetchColumn()`.
2. **Native Array & JSON Support**:
   - PostgreSQL arrays and JSONB columns can be queried directly with native operators.
3. **No true boolean stringification**:
   - PostgreSQL is strict about boolean types (`true` / `false` instead of `1` / `0`).

---

## 3. SQLite (`pdo_sqlite`)

- **Extension**: `extension=pdo_sqlite`
- **Underlying Engine**: SQLite3 (embedded, serverless)

### DSN Formats

```php
// Absolute path on disk
$dsn = 'sqlite:/var/data/app.sqlite';

// Relative path on disk
$dsn = 'sqlite:./storage/database.db';

// In-memory database (persists only for connection lifetime)
$dsn = 'sqlite::memory:';

// Temporary disk-backed database (destroyed on close)
$dsn = 'sqlite:';
```

### Essential PRAGMAs for Production SQLite

Always execute these immediately upon opening an SQLite connection:

```php
$pdo = new PDO('sqlite:/path/to/db.sqlite');

// 1. Enable Foreign Keys (OFF by default in SQLite!)
$pdo->exec('PRAGMA foreign_keys = ON;');

// 2. Enable Write-Ahead Logging (vastly improves concurrent read/write throughput)
$pdo->exec('PRAGMA journal_mode = WAL;');

// 3. Set Busy Timeout to avoid "database is locked" errors under load (5000ms = 5s)
$pdo->exec('PRAGMA busy_timeout = 5000;');

// 4. Safe sync mode with WAL
$pdo->exec('PRAGMA synchronous = NORMAL;');
```

---

## 4. Microsoft SQL Server (`pdo_sqlsrv` / `pdo_dblib`)

### Official Microsoft Driver (`pdo_sqlsrv`)

- **Extension**: `extension=pdo_sqlsrv`
- **Platform**: Windows & Linux (via MS ODBC Driver for SQL Server)

```php
$dsn = 'sqlsrv:Server=127.0.0.1,1433;Database=MyDatabase;TrustServerCertificate=true;Encrypt=true';
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::SQLSRV_ATTR_DIRECT_QUERY => false,
]);
```

### FreeTDS / Sybase Driver (`pdo_dblib`)

- **Extension**: `extension=pdo_dblib`
- **Platform**: Common on older Linux servers using FreeTDS

```php
$dsn = 'dblib:host=127.0.0.1:1433;dbname=MyDatabase;charset=UTF-8';
$pdo = new PDO($dsn, $user, $password);
```

---

## 5. Oracle Database (`pdo_oci`)

- **Extension**: `extension=pdo_oci`
- **Prerequisite**: Oracle Instant Client / SDK

### DSN Formats

```php
// Easy Connect format
$dsn = 'oci:dbname=//localhost:1521/XEPDB1;charset=AL32UTF8';

// TNS format
$dsn = 'oci:dbname=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=ORCL)));charset=AL32UTF8';
```

---

## 6. ODBC (`pdo_odbc`)

- **Extension**: `extension=pdo_odbc`
- Connects to any data source providing an ODBC driver (Access, DB2, AS/400, Snowflake, etc.).

```php
// Pre-configured System DSN
$dsn = 'odbc:DSN=MySystemDsn;UID=user;PWD=secret';

// Driver-direct DSN (DSN-less connection)
$dsn = 'odbc:Driver={PostgreSQL Unicode};Server=localhost;Port=5432;Database=testdb;';
```

---

## 7. Firebird (`pdo_firebird`)

- **Extension**: `extension=pdo_firebird`

```php
$dsn = 'firebird:dbname=localhost:/path/to/database.fdb;charset=UTF8';
$pdo = new PDO($dsn, $user, $password);
```
