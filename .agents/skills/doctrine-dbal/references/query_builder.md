# Doctrine DBAL QueryBuilder Reference

The DBAL QueryBuilder allows dynamic SQL construction with programmatic parameters, composite conditions, and automatic cross-platform escaping.

---

## 1. Select Queries & Expression Builder

Use the ExpressionBuilder (`$qb->expr()`) to construct complex boolean expressions safely:

```php
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

$qb = $conn->createQueryBuilder();

$expr = $qb->expr();

$qb->select('u.id', 'u.name', 'u.email', 'p.bio')
   ->from('users', 'u')
   ->innerJoin('u', 'profiles', 'p', 'p.user_id = u.id')
   ->where(
       $expr->and(
           $expr->eq('u.status', ':status'),
           $expr->or(
               $expr->gt('u.reputation', ':min_rep'),
               $expr->isNotNull('u.verified_at')
           )
       )
   )
   ->andWhere('u.role IN (:roles)')
   ->setParameter('status', 'active', ParameterType::STRING)
   ->setParameter('min_rep', 100, ParameterType::INTEGER)
   ->setParameter('roles', ['moderator', 'admin'], ArrayParameterType::STRING)
   ->orderBy('u.created_at', 'DESC')
   ->setFirstResult(0)  // OFFSET
   ->setMaxResults(25); // LIMIT

$results = $qb->fetchAllAssociative();
```

---

## 2. Dynamic Update Queries

```php
$qb = $conn->createQueryBuilder();

$qb->update('users', 'u')
   ->set('u.last_login_at', ':now')
   ->set('u.login_count', 'u.login_count + 1')
   ->where('u.id = :user_id')
   ->setParameter('now', date('Y-m-d H:i:s'))
   ->setParameter('user_id', 42, ParameterType::INTEGER);

$affectedRows = $qb->executeStatement();
```

---

## 3. Dynamic Insert Queries

```php
$qb = $conn->createQueryBuilder();

$qb->insert('audit_logs')
   ->values([
       'action'     => ':action',
       'user_id'    => ':user_id',
       'created_at' => ':created_at',
   ])
   ->setParameters([
       'action'     => 'password_change',
       'user_id'    => 42,
       'created_at' => date('Y-m-d H:i:s'),
   ], [
       'user_id'    => ParameterType::INTEGER,
   ]);

$qb->executeStatement();
```

---

## 4. Dynamic Delete Queries

```php
$qb = $conn->createQueryBuilder();

$qb->delete('sessions')
   ->where('expires_at < :now')
   ->setParameter('now', date('Y-m-d H:i:s'));

$deletedCount = $qb->executeStatement();
```

---

## 5. Parameter Types Table

| Parameter Type | DBAL Constant | Notes |
| :--- | :--- | :--- |
| Integer | `ParameterType::INTEGER` | Binds as 32/64 bit integer |
| String | `ParameterType::STRING` | Binds as standard string |
| Boolean | `ParameterType::BOOLEAN` | Crucial for PostgreSQL |
| Null | `ParameterType::NULL` | Explicit null |
| Binary / LOB | `ParameterType::BINARY` / `LARGE_OBJECT` | BLOBs, raw bytes |
| Array of Ints | `ArrayParameterType::INTEGER` | Used with `IN (:ids)` |
| Array of Strings | `ArrayParameterType::STRING` | Used with `IN (:roles)` |
| Array of ASCII | `ArrayParameterType::ASCII` | Optimized ASCII lists |
