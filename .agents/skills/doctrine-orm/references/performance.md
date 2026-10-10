# Doctrine ORM Performance Tuning Reference

Checklist and strategies to keep Doctrine ORM applications fast, lean, and memory-efficient.

---

## 1. Eliminate the N+1 Query Problem

### The Symptom:
A single database query loads 100 users, and a loop over their orders triggers 100 separate `SELECT` queries for each user's orders.

### The Fix:
Always use **Fetch Joins** in your custom repositories:

```php
// Eagerly hydrates the relation in 1 single SQL JOIN query:
$qb->select('u', 'o')
   ->from(User::class, 'u')
   ->leftJoin('u.orders', 'o');
```

---

## 2. Batch Processing & Memory Management

The Identity Map keeps a reference to every loaded entity until `$em->clear()` is called. Without clearing, long-running loops or CLI jobs will crash with an out-of-memory error.

### The Batch Pattern:

```php
$q = $em->createQuery('SELECT u FROM App\Entity\User u');
$iterableResult = $q->toIterable();

$batchSize = 200;
$i = 0;

foreach ($iterableResult as $user) {
    $user->calculateScore();

    if ((++$i % $batchSize) === 0) {
        $em->flush(); // Writes changes to database
        $em->clear(); // Detaches all entities, freeing RAM
    }
}

$em->flush();
$em->clear();
```

---

## 3. Read-Only Queries: Array Hydration

When rendering views, exports, or JSON APIs where entities will **not** be modified, bypass object hydration completely:

```php
// Returns plain PHP associative arrays:
// 3x to 5x faster, uses 70% less memory than hydrating objects
$users = $query->getArrayResult();
```

---

## 4. Second-Level Cache (L2 Cache)

Doctrine supports a second-level distributed cache (Redis, APCu, Memcached) to cache entity state and query results between distinct HTTP requests:

```php
#[ORM\Entity]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE', region: 'catalog_region')]
class Category
{
    // ...
}
```

Enable query caching on frequently read queries:
```php
$query->setCacheable(true);
$query->setLifetime(3600); // 1 hour
```

---

## 5. Indexing Strategy

Always index foreign keys and columns used in `WHERE`, `ORDER BY`, or `JOIN`:

```php
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_orders_status_created', columns: ['status', 'created_at'])]
class Order
{
    // ...
}
```
