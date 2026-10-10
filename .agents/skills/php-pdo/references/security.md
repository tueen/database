# PDO Security Guide: Defense-in-Depth

This guide details essential security practices for building resilient database applications with PHP Data Objects (PDO).

---

## 1. SQL Injection Prevention

SQL Injection occurs when user-supplied input is directly concatenated or interpolated into an SQL query string, altering the query's AST (Abstract Syntax Tree).

### The Golden Rule: Prepared Statements with Parameters

Parameters represent data values only. The database engine parses and compiles the query structure *before* values are bound, making it impossible for input to inject new SQL commands.

```php
// VULNERABLE TO SQL INJECTION:
$sql = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";
$pdo->query($sql);

// SECURE:
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute(['email' => $_POST['email']]);
$user = $stmt->fetch();
```

---

## 2. Why Emulated Prepares Must Be Disabled

By default in older PHP setups, PDO emulates prepared statements by substituting placeholders with escaped strings in PHP before sending the query.

Setting `PDO::ATTR_EMULATE_PREPARES => false`:
1. Sends the query template and values in two separate packets using the native binary protocol (mysqlnd / libpq).
2. Prevents multi-statement execution exploits (`SELECT ...; DROP TABLE ...;`).
3. Preserves exact data types (integers remain `int`, floats remain `float`).

---

## 3. The Multi-Byte Encoding Exploit (GBK / Big5)

If you use MySQL with a multi-byte character set and fail to define the charset in the DSN, an attacker can craft multibyte sequences (like `0xbf 0x27` in GBK) where `addslashes` or escaping converts `0x27` (`'`) into `\'` (`0x5c 0x27`). The byte `0xbf 0x5c` forms a valid multibyte character, leaving the single quote `0x27` unescaped!

### Remedy:
Always define the charset inside the DSN string:
```php
// SECURE
$dsn = 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4';
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_EMULATE_PREPARES => false,
]);
```

---

## 4. Securing Dynamic Identifiers (Columns & Tables)

SQL parameter placeholders (`?`, `:param`) **cannot** represent database identifiers (table names, column names, `ASC`/`DESC`).

```php
// WRONG - Parameter placeholder will trigger syntax error or be treated as a string literal:
$stmt = $pdo->prepare('SELECT * FROM users ORDER BY :sort_column');
```

### Whitelisting Pattern (The Only Safe Method)

Validate any dynamic identifier against a strict hardcoded whitelist before using it:

```php
$allowedColumns = [
    'id'         => 'id',
    'username'   => 'username',
    'created_at' => 'created_at',
];

$sortInput = $_GET['sort'] ?? 'id';
$sortColumn = $allowedColumns[$sortInput] ?? 'id'; // Strict fallback

$direction = (strtoupper($_GET['dir'] ?? '') === 'DESC') ? 'DESC' : 'ASC';

// Safe to concatenate strictly validated identifiers
$sql = "SELECT id, username, created_at FROM users ORDER BY {$sortColumn} {$direction}";
$stmt = $pdo->query($sql);
```

---

## 5. Preventing Second-Order SQL Injection

Second-order injection occurs when data previously saved into a database (often cleaned or parameterized on initial insert) is later retrieved and concatenated directly into another query without parameterization.

**Rule**: Treat all data retrieved from the database as potentially untrusted if it originated from external sources. Always use prepared statements when querying with values fetched from earlier queries.

---

## 6. Information Leakage & Exception Handling

Database exceptions (`PDOException`) often contain sensitive information: database usernames, server hostnames, internal table structures, and parts of the query text.

### In Production:
- Never display raw exception details to the end-user.
- Log the details to secure log files.
- Return a generic error message or HTTP 500 error.

```php
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // 1. Log full details internally
    error_log(sprintf('[DB Error] Code: %s, Message: %s', $e->getCode(), $e->getMessage()));
    
    // 2. Present generic error to client
    http_response_code(500);
    echo json_encode(['error' => 'An internal database error occurred. Please contact support.']);
    exit;
}
```

---

## 7. Credential Management

- Never commit database credentials into version control (`git`).
- Use environment variables (`getenv()`, `$_ENV`, or `.env` files located strictly outside the public web root).
- Ensure `.env` is added to `.gitignore`.
- Follow the principle of least privilege: the database user configured for the web app should not have `DROP DATABASE`, `CREATE USER`, or `GRANT` privileges.
