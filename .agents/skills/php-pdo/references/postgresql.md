# PostgreSQL with PDO (`pdo_pgsql`)

Complete guide for configuring, querying, and optimizing PostgreSQL databases using PHP Data Objects.

---

## 1. DSN Specification & Parameters

PostgreSQL connection strings can include connection parameters, SSL modes, and server configuration switches directly inside the DSN.

```php
$dsn = "pgsql:host=localhost;port=5432;dbname=my_database;sslmode=prefer;options='--client_encoding=UTF8'";
```

### Multi-Tenancy via `search_path`

In PostgreSQL, you can isolate tenants or schemas by configuring the `search_path` in the DSN options:

```php
// Sets schema resolution order to "tenant_42", falling back to "public"
$dsn = "pgsql:host=localhost;port=5432;dbname=my_app;options='-c search_path=tenant_42,public'";
```

Alternatively, set it immediately after connecting:
```php
$pdo->exec('SET search_path TO tenant_42, public');
```

---

## 2. Standard Configuration Options

```php
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
];

$pdo = new PDO($dsn, $user, $password, $options);
```

---

## 3. Retrieving Generated IDs: `RETURNING` vs `lastInsertId`

In PostgreSQL, tables typically use `IDENTITY` columns or `SERIAL` sequences. 

### Why You Should Prefer the `RETURNING` Clause

Unlike MySQL, PostgreSQL supports returning any generated or computed column directly from `INSERT`, `UPDATE`, or `DELETE`:

```php
// Modern, robust approach (no sequence name required)
$stmt = $pdo->prepare('
    INSERT INTO users (name, email, role)
    VALUES (:name, :email, :role)
    RETURNING id, created_at
');

$stmt->execute([
    'name'  => 'Bob',
    'email' => 'bob@example.com',
    'role'  => 'editor',
]);

$inserted = $stmt->fetch();
$userId = (int) $inserted['id'];
$createdAt = $inserted['created_at'];
```

### Using `lastInsertId()`

If using `$pdo->lastInsertId()`, you **must** supply the sequence name:
```php
$userId = (int) $pdo->lastInsertId('users_id_seq');
```

---

## 4. PostgreSQL Upserts (`ON CONFLICT`)

PostgreSQL provides native upsert semantics via `ON CONFLICT`:

```php
$sql = '
    INSERT INTO user_preferences (user_id, theme, notifications_enabled)
    VALUES (:user_id, :theme, :notifications)
    ON CONFLICT (user_id) 
    DO UPDATE SET 
        theme = EXCLUDED.theme,
        notifications_enabled = EXCLUDED.notifications_enabled,
        updated_at = NOW()
    RETURNING id
';

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'user_id'       => $userId,
    'theme'         => 'dark',
    'notifications' => true,
]);
```

---

## 5. Handling Native PostgreSQL Types

### A. Booleans
PostgreSQL is strictly typed regarding booleans (`BOOLEAN`). When passing boolean values via PDO:

```php
$stmt = $pdo->prepare('UPDATE accounts SET is_verified = :verified WHERE id = :id');
// Use explicit PDO::PARAM_BOOL or true/false boolean types
$stmt->bindValue(':verified', true, PDO::PARAM_BOOL);
$stmt->bindValue(':id', 15, PDO::PARAM_INT);
$stmt->execute();
```

### B. JSON & JSONB
Encode data to valid JSON strings before passing to PostgreSQL. Postgres will automatically validate the JSON structure:

```php
$metadata = ['last_ip' => '192.168.1.1', 'device' => 'mobile'];

$stmt = $pdo->prepare('UPDATE users SET metadata = :metadata::jsonb WHERE id = :id');
$stmt->execute([
    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
    'id'       => 15,
]);

// Querying inside JSONB:
$stmt = $pdo->prepare("SELECT * FROM users WHERE metadata->>'device' = :device");
$stmt->execute(['device' => 'mobile']);
$users = $stmt->fetchAll();
```

---

## 6. PostgreSQL Specific SQLSTATE Error Codes

PostgreSQL returns standard ANSI SQLSTATE codes. You can check `$e->getCode()` to gracefully handle constraint violations:

| SQLSTATE | Condition | Handling Strategy |
| :--- | :--- | :--- |
| `23505` | Unique violation (`unique_violation`) | Show "Email already exists" / duplicate error |
| `23503` | Foreign key violation (`foreign_key_violation`) | Invalid referenced ID |
| `23502` | Not null violation (`not_null_violation`) | Missing mandatory field |
| `40001` | Serialization failure / Deadlock | Retry transaction |
| `57014` | Query canceled (timeout) | Optimize query or increase `statement_timeout` |

### Example Constraint Handling

```php
try {
    $stmt = $pdo->prepare('INSERT INTO users (email) VALUES (:email)');
    $stmt->execute(['email' => $email]);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        throw new DomainException('A user with this email address already exists.');
    }
    throw $e;
}
```
