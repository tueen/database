# Doctrine DQL & ORM QueryBuilder Reference

Doctrine Query Language (DQL) queries against your **object model** (Entities and Properties), not database tables and columns.

---

## 1. DQL Syntax Fundamentals

```php
// DQL references Class App\Entity\User and its property 'email', NOT table 'users'
$query = $em->createQuery('
    SELECT u, o
    FROM App\Entity\User u
    JOIN u.orders o
    WHERE u.email = :email AND o.status = :status
');

$query->setParameter('email', 'john@example.com');
$query->setParameter('status', 'shipped');

$users = $query->getResult();
```

---

## 2. ORM QueryBuilder Best Practices

Always build queries using entity aliases and relation paths:

```php
$qb = $em->createQueryBuilder();

$qb->select('u')
   ->addSelect('o') // Prevents N+1 by hydrating orders in the same query
   ->from(App\Entity\User::class, 'u')
   ->leftJoin('u.orders', 'o')
   ->where('u.createdAt >= :startDate')
   ->andWhere('o.total > :minTotal')
   ->setParameter('startDate', new DateTimeImmutable('-90 days'))
   ->setParameter('minTotal', 50.00)
   ->orderBy('o.createdAt', 'DESC')
   ->setFirstResult(0)
   ->setMaxResults(50);

$query = $qb->getQuery();
$results = $query->getResult();
```

---

## 3. Hydration Modes

Doctrine supports multiple ways to hydrate query results:

| Mode | Method / Constant | Returned Type | Best Used For |
| :--- | :--- | :--- | :--- |
| **Object** (Default) | `$query->getResult()` | `list<Entity>` | Modifying entities, full domain model |
| **Array** | `$query->getArrayResult()` | `list<array>` | Read-only views, JSON API responses |
| **Single Scalar** | `$query->getSingleScalarResult()` | `mixed` (e.g. `int`) | `COUNT()`, `MAX()`, `SUM()` |
| **Single Column** | `$query->getSingleColumnResult()` | `list<mixed>` | Flat list of IDs or emails |

### DTO Projection (`NEW` Syntax)

Create read-only Data Transfer Objects directly inside DQL without hydrating full managed entities:

```php
$dql = '
    SELECT NEW App\DTO\UserSummary(u.id, u.name, COUNT(o.id))
    FROM App\Entity\User u
    LEFT JOIN u.orders o
    GROUP BY u.id, u.name
';

$summaries = $em->createQuery($dql)->getResult();
// Returns an array of App\DTO\UserSummary instances
```

---

## 4. Safe Pagination with `Doctrine\ORM\Tools\Pagination\Paginator`

Standard SQL `LIMIT / OFFSET` produces incorrect results when joining `to-many` collections (because rows are multiplied by joined items). Always use Doctrine's `Paginator`:

```php
use Doctrine\ORM\Tools\Pagination\Paginator;

$qb = $em->createQueryBuilder()
    ->select('u', 'o')
    ->from(User::class, 'u')
    ->leftJoin('u.orders', 'o')
    ->orderBy('u.id', 'ASC')
    ->setFirstResult(20) // Page 2 (20 items per page)
    ->setMaxResults(20);

$paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

$totalCount = count($paginator); // Total distinct users
foreach ($paginator as $user) {
    echo $user->getName();
}
```
