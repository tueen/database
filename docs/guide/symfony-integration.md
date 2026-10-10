# Symfony DI & Events

Tueen Database integrates seamlessly with Symfony components, including **DependencyInjection**, **EventDispatcher**, **Validator**, **Serializer**, and **Security**.

---

## 🔌 Symfony DependencyInjection

You can compile a complete dependency injection container containing `DatabaseManager`, repositories, and password hashing services:

```php
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\DependencyInjection\ContainerFactory;

$config = DatabaseConfig::createMysql(
    dbname: 'telegram_bot',
    user: 'bot_admin',
    password: 'password',
);

$container = ContainerFactory::createContainer($config);

// Retrieve services from container
$db = $container->get(DatabaseManager::class);
$hasher = $container->get(PasswordHasherService::class);
```

---

## 📢 Domain Events (`EventDispatcher`)

Tueen Database dispatches Symfony contracts events across lifecycle changes:

| Event Class | Dispatched When |
| :--- | :--- |
| `Tueen\Database\Event\ChatCreatedEvent` | A new chat is registered via `findOrCreate` |
| `Tueen\Database\Event\BalanceChangedEvent` | Funds are credited or debited on a wallet |
| `Tueen\Database\Event\OrderStatusChangedEvent` | An order is marked as paid, completed, or refunded |
| `Tueen\Database\Event\MigrationExecutedEvent` | Schema sync or versioned migration completes |

### Listening to Events

```php
use Tueen\Database\Event\BalanceChangedEvent;

$db->getEventDispatcher()->addListener(BalanceChangedEvent::class, function (BalanceChangedEvent $event) {
    echo "Balance changed for chat {$event->chat->id} by {$event->amount} {$event->currency}!\n";
    echo "New Balance: {$event->newBalance}\n";
});
```

---

## 🧭 Next Steps

- Review the **[Entity Models Catalog](/reference/entities)**.
- Browse **[Domain Repositories](/reference/repositories)**.
