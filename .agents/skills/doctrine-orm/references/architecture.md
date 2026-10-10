# Doctrine ORM Architecture Reference

Understanding Doctrine's internal architecture is critical for writing correct, high-performance database interactions.

---

## 1. Data Mapper vs. Active Record

- **Active Record (e.g. Eloquent, Rails)**: An entity class combines both business logic AND database access (`$user->save()`, `$user->delete()`). Entities are tightly coupled to the database schema.
- **Data Mapper (Doctrine)**: Entities are Plain Old PHP Objects (POPOs). Persistence logic is strictly separated into the `EntityManager`.
  - Enables clean Domain-Driven Design (DDD).
  - Business entities can be tested without a database connection.
  - Changes to multiple objects are coordinated and executed in optimal order.

---

## 2. The Unit of Work (`UoW`)

The `UnitOfWork` is the engine inside the `EntityManager`. It maintains a list of objects affected by a business transaction and coordinates the writing out of changes.

### Entity States
1. **New**: An instantiated object (`$user = new User()`) not yet known to the EntityManager.
2. **Managed**: The object has a database identity and its property changes are actively tracked by the Unit of Work.
3. **Detached**: The object has a database identity, but is no longer tracked by the Unit of Work (e.g. after `$em->clear()` or `$em->detach($entity)`).
4. **Removed**: Marked for deletion. At the next `flush()`, a `DELETE` SQL statement will be executed.

```
       [new]
         │
         │ $em->persist()
         ▼
     [MANAGED] ◄────────┐
      │     │           │ $em->find()
      │     │ $em->clear()
      │     ▼           │
      │   [DETACHED] ───┘
      │
      │ $em->remove()
      ▼
   [REMOVED]
      │
      │ $em->flush()
      ▼
  (DELETED FROM DB)
```

---

## 3. The Identity Map Pattern

Doctrine maintains an internal registry of all managed entities indexed by their primary key.

```php
$user1 = $em->find(User::class, 1);
$user2 = $em->find(User::class, 1);

// Identity Map guarantees both variables reference the exact same object in memory:
var_dump($user1 === $user2); // TRUE! No second SQL query is executed.
```

---

## 4. Proxies and Ghost Objects

To support lazy loading (loading related entities only when their properties or methods are accessed), Doctrine generates Proxy objects (or Ghost Objects in modern Doctrine 3 / Symfony var-exporter):

```php
// If order #10 belongs to user #5:
$order = $em->find(Order::class, 10);

// $user is initially an uninitialized Proxy object containing only id = 5
$user = $order->getUser(); 

// Accessing any non-id property triggers the SQL SELECT transparently:
echo $user->getName(); // Executes "SELECT ... FROM users WHERE id = 5"
```
