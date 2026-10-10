# Doctrine ORM Mapping with PHP 8 Attributes Reference

Comprehensive reference for mapping database structures to PHP entity properties using native PHP 8 Attributes.

---

## 1. Class-Level Attributes

```php
use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'shop_products')]
#[ORM\Index(name: 'idx_product_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_product_sku', columns: ['sku'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    // ...
}
```

---

## 2. Primary Keys & ID Generation Strategies

```php
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// 1. Auto-increment / Serial (Default & Most Common)
#[ORM\Id]
#[ORM\GeneratedValue(strategy: 'AUTO')]
#[ORM\Column(type: Types::INTEGER)]
private ?int $id = null;

// 2. PostgreSQL Sequence
#[ORM\Id]
#[ORM\GeneratedValue(strategy: 'SEQUENCE')]
#[ORM\SequenceGenerator(sequenceName: 'users_id_seq', allocationSize: 1, initialValue: 1)]
#[ORM\Column(type: Types::INTEGER)]
private ?int $id = null;

// 3. UUID / Natural Key (Assigned manually in constructor)
#[ORM\Id]
#[ORM\Column(type: Types::STRING, length: 36)]
private string $id;
```

---

## 3. Association Mappings

### Many-To-One & One-To-Many (Bidirectional)

The `ManyToOne` side is always the **Owning Side** (the table containing the foreign key column `user_id`):

```php
// Order.php (Owning Side)
#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
private User $user;
```

The `OneToMany` side is the **Inverse Side**:
```php
// User.php (Inverse Side)
use Doctrine\Common\Collections\Collection;

/** @var Collection<int, Order> */
#[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
private Collection $orders;
```

### Many-To-Many (with Join Table)

```php
// Article.php (Owning Side)
#[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
#[ORM\JoinTable(name: 'article_tags')]
#[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id')]
#[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
private Collection $tags;

// Tag.php (Inverse Side)
#[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
private Collection $articles;
```

### One-To-One

```php
// User.php
#[ORM\OneToOne(targetEntity: Profile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
private ?Profile $profile = null;

// Profile.php (Owning Side)
#[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
#[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, unique: true)]
private User $user;
```

---

## 4. Lifecycle Callbacks

Execute logic automatically at specific points in the entity persistence lifecycle:

```php
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Article
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
```
Available Lifecycle Callbacks:
- `PrePersist` / `PostPersist`
- `PreUpdate` / `PostUpdate`
- `PreRemove` / `PostRemove`
- `PostLoad`
