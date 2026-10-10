---
name: doctrine-orm
description: >-
  Expert guidance and best practices for Doctrine ORM (Object-Relational Mapper).
  Use when designing entities with PHP 8 attributes, bootstrapping the EntityManager,
  managing entity lifecycles with the Unit of Work, defining associations (OneToOne,
  OneToMany, ManyToMany), authoring DQL and ORM QueryBuilder queries, preventing N+1
  query bottlenecks, and batch processing large datasets.
---

# Doctrine ORM Skill

This skill provides comprehensive architectural guidelines, configuration instructions, and production-tested patterns for **Doctrine ORM** in modern PHP (PHP 8.1+ / Doctrine ORM 3.x & 2.x), based on the [official Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/current/).

---

## 1. Core Architecture & Mental Model

Doctrine ORM is an implementation of the **Data Mapper** pattern with an **Identity Map** and **Unit of Work**:
- **Entities are Plain PHP Objects (POPOs)**: Entities have no knowledge of database connection, SQL, or persistence logic (unlike ActiveRecord).
- **EntityManager (`EM`)**: Central facade for managing entity persistence and querying.
- **Unit of Work (`UoW`)**: Tracks all changes to managed entities and computes minimal, batched SQL updates at `flush()` time.
- **Identity Map**: Guarantees that each database row corresponds to exactly one PHP object instance within a single EntityManager session.

---

## 2. Bootstrapping the EntityManager (Modern Doctrine 3+)

```php
<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$paths = [__DIR__ . '/src/Entity'];
$isDevMode = true; // false in production

// 1. Setup metadata configuration using native PHP 8 Attributes
$config = ORMSetup::createAttributeMetadataConfiguration(
    paths: $paths,
    isDevMode: $isDevMode
);

// 2. Setup database connection parameters
$connectionParams = [
    'driver'   => 'pdo_mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'app_db',
    'user'     => 'app_user',
    'password' => 'secret',
    'charset'  => 'utf8mb4',
];

$connection = DriverManager::getConnection($connectionParams, $config);

// 3. Instantiate EntityManager (ORM 3 constructor pattern)
$entityManager = new EntityManager($connection, $config);
```

---

## 3. Defining Entities with Modern PHP 8 Attributes

Never use deprecated docblock annotations (`@ORM\...`). Use native PHP 8 attributes under the `Doctrine\ORM\Mapping` namespace (aliased as `ORM`):

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 150)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orders;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->createdAt = new DateTimeImmutable();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): void
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }
    }

    public function removeOrder(Order $order): void
    {
        if ($this->orders->removeElement($order)) {
            // orphanRemoval will delete the order in the database
        }
    }
}
```

---

## 4. Entity Lifecycle: `persist()`, `flush()`, and `remove()`

Understanding entity states is fundamental to working with Doctrine:
- **New**: Object created with `new`, not yet tracked by Unit of Work.
- **Managed**: Tracked by Unit of Work. Changes are automatically detected.
- **Detached**: Formerly managed, but separated via `$em->clear()` or `$em->detach()`.
- **Removed**: Scheduled for deletion on next `flush()`.

```php
// 1. Creating and persisting a new entity
$user = new User('Sarah', 'sarah@example.com');
$entityManager->persist($user); // Now managed

// 2. Updating an entity (NO need to call persist on already managed entities!)
$user->setName('Sarah Connor');

// 3. Commit all changes to the database in a single atomic transaction
$entityManager->flush();

// 4. Removing an entity
$entityManager->remove($user);
$entityManager->flush();
```

> [!WARNING]
> Do NOT call `$em->persist()` on an entity that is already managed (e.g. loaded via repository). The Unit of Work automatically tracks changes on managed entities via dirty checking.

---

## 5. Querying: DQL & ORM QueryBuilder

### Resolving the N+1 Query Problem with Fetch Joins

The most common performance pitfall in ORM applications is loading relations in a loop, causing N+1 queries. Solve this with `JOIN FETCH` (or `->addSelect()` in QueryBuilder):

```php
use App\Entity\User;

// WRONG (Triggers 1 query for users + N queries for orders):
// $users = $em->getRepository(User::class)->findAll();
// foreach ($users as $user) { echo count($user->getOrders()); }

// CORRECT (Single query with Eager Fetch Join):
$qb = $entityManager->createQueryBuilder();

$qb->select('u', 'o')
   ->from(User::class, 'u')
   ->leftJoin('u.orders', 'o')
   ->where('u.createdAt >= :since')
   ->setParameter('since', new DateTimeImmutable('-30 days'))
   ->orderBy('u.createdAt', 'DESC');

$usersWithOrders = $qb->getQuery()->getResult();
```

---

## 6. Batch Processing Large Datasets

When inserting or updating thousands of rows, clear the EntityManager periodically to prevent memory exhaustion:

```php
$batchSize = 100;

for ($i = 1; $i <= 10000; ++$i) {
    $log = new AuditLog("Action #$i");
    $entityManager->persist($log);

    if (($i % $batchSize) === 0) {
        $entityManager->flush(); // Write batch to DB
        $entityManager->clear(); // Detach all entities to free memory!
    }
}

$entityManager->flush();
$entityManager->clear();
```

---

## 7. Custom Repository Pattern

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<User>
 */
class UserRepository extends EntityRepository
{
    /**
     * @return list<User>
     */
    public function findActiveUsersWithRecentOrders(int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('o')
            ->innerJoin('u.orders', 'o')
            ->where('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
```

---

## 8. References & Examples Navigation

- **Architecture Deep-Dive**: [references/architecture.md](./references/architecture.md) - Unit of Work, Identity Map, Proxy objects, and entity states.
- **Attributes Mapping Complete Guide**: [references/attributes_mapping.md](./references/attributes_mapping.md) - OneToOne, OneToMany, ManyToMany, inheritance mapping, and lifecycle hooks.
- **DQL & Querying Guide**: [references/querying_dql.md](./references/querying_dql.md) - DQL syntax, pagination, DTO projection, and expression building.
- **Performance Tuning**: [references/performance.md](./references/performance.md) - Hydration modes, second-level cache, indexing, and partial object rules.
- **Runnable Bootstrap Example**: [examples/bootstrap_orm.php](./examples/bootstrap_orm.php) - Production EntityManager factory setup.
- **Entity Model Example**: [examples/EntityExample.php](./examples/EntityExample.php) - Fully mapped entities with bi-directional relations.
- **Custom Repository Example**: [examples/RepositoryExample.php](./examples/RepositoryExample.php) - Production repository implementation.
