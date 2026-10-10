# Domain Repositories Reference

Tueen Database provides rich repository classes accessible via fluent methods on `DatabaseManager`.

---

## 🏛️ Repositories Overview

| Repository Accessor | Return Type | Primary Purpose |
| :--- | :--- | :--- |
| `$db->chats()` | `ChatRepository` | User/chat registration, normalization, ban management |
| `$db->contacts()` | `ContactRepository` | User phone numbers, vCard data, verified identity profiles |
| `$db->chatXp()` | `ChatXpRepository` | Gamification experience points (XP), leaderboards, audit logs |
| `$db->wallets()` | `WalletRepository` | Multi-currency balances, credits, debits, P2P transfers, freezing |
| `$db->withdrawals()` | `WithdrawalRepository` | Payout requests, approvals, balance unfreeze/settlement |
| `$db->treasury()` / `$db->treasuries()` | `TreasuryRepository` | Operator reserve tracking and commission income/expense logging |
| `$db->orders()` | `OrderRepository` | Order creation, payments, invoice tracking, discount snapshots |
| `$db->coupons()` | `CouponRepository` | Coupon validation, usage quotas, user limit enforcement |
| `$db->subscriptions()` | `SubscriptionRepository`| Time-limited memberships, active checks, renewal logs |
| `$db->broadcasts()` | `BroadcastRepository` | Mass messaging campaigns, chunked delivery, cursor offsets |
| `$db->referrals()` | `ReferralRepository` | Viral affiliate registration, self-referral blocks, qualification |
| `$db->entities()` | `EntityItemRepository` | Generic CMS content catalog (products, tickets, FAQs, likes, ratings) |
| `$db->messageRelays()` | `MessageRelayRepository`| Cross-chat message bridging (support ticketing, admin relays) |
| `$db->options()` | `OptionRepository` | Persistent global options with in-memory caching |
| `$db->cache()` | `CacheRepository` | PSR-16 SimpleCache database store with TTL purging |
| `$db->data()` | `DataRepository` | Arbitrary key-value and binary BLOB store scoped by chat/group |
| `$db->tmaSessions()` / `$db->sessions()` | `TmaSessionRepository` | Telegram Mini App bearer session issuance and auth |
| `$db->histories()` | `HistoryRepository` | Universal polymorphic activity logs and chronological timelines |

---

## 💻 Essential Method Signatures

### `ChatRepository`

```php
public function findOrCreate(
    int $chatId,
    ?string $username = null,
    ?string $name = null,
    ChatType $type = ChatType::Private,
    ?string $languageCode = null,
): Chat;

public function findByUsername(string $username): ?Chat;
public function findActive(int $limit = 100, int $offset = 0): array;
public function ban(int $chatId): void;
public function unban(int $chatId): void;
public function setAccessible(int $chatId, bool $accessible): void;
```

---

### `ContactRepository`

```php
public function saveContact(
    Chat|int $user,
    string $phoneNumber,
    string $firstName,
    ?string $lastName = null,
    ?string $vcard = null,
): Contact;

public function findByPhoneNumber(string $phoneNumber): ?Contact;
public function findByChat(Chat|int $chat): ?Contact;
```

---

### `ChatXpRepository`

```php
public function getXp(Chat|int $chat): int;

public function addXp(
    Chat|int $chat,
    int $amount,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): ChatXpTransaction;

public function deductXp(
    Chat|int $chat,
    int $amount,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): ChatXpTransaction;

public function getLeaderboard(int $limit = 10, int $offset = 0): array;
public function getTransactions(Chat|int $chat, int $limit = 50, int $offset = 0): array;
```

---

### `WalletRepository`

```php
public function getBalance(Chat|int $chat, ?string $currency = null): string;

public function credit(
    Chat|int $chat,
    string $amount,
    ?string $currency = null,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): WalletTransaction;

public function debit(
    Chat|int $chat,
    string $amount,
    ?string $currency = null,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): WalletTransaction;

public function transfer(
    Chat|int $fromChat,
    Chat|int $toChat,
    string $amount,
    ?string $currency = null,
    ?string $source = 'transfer',
): array;

public function freeze(Chat|int $chat, string $amount, ?string $currency = null): string;
public function unfreeze(Chat|int $chat, string $amount, ?string $currency = null): string;
```

> **Note on Currency:** If `$currency` is `null`, the repository automatically uses the database's configured `$defaultCurrency` (defaults to `XTR` - Telegram Stars, customizable via `DatabaseConfig` or `TUEEN_DEFAULT_CURRENCY` in `.env`).

---

### `WithdrawalRepository`

```php
public function request(
    Chat|int $chat,
    string $amount,
    ?string $currency = null,
    string $feeAmount = '0.000000000000000000',
    string $commissionRate = '0.00',
    ?string $payoutMethod = null,
    ?string $destination = null,
    bool $freezeBalance = true,
): Withdrawal;

public function approve(Withdrawal|int $withdrawal, ?string $txHash = null): Withdrawal;
public function reject(Withdrawal|int $withdrawal, ?string $reason = null): Withdrawal;
public function complete(Withdrawal|int $withdrawal, ?string $txHash = null): Withdrawal;
public function cancel(Withdrawal|int $withdrawal, ?string $reason = null): Withdrawal;
```

---

### `TreasuryRepository`

```php
public function getBalance(?string $currency = null): string;

public function recordIncome(
    string $amount,
    ?string $currency = null,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): TreasuryTransaction;

public function recordExpense(
    string $amount,
    ?string $currency = null,
    ?string $source = null,
    ?int $sourceId = null,
    ?Chat $createdBy = null,
): TreasuryTransaction;
```

---

### `OrderRepository`

```php
public function createOrder(
    string $type,
    Chat|int $chat,
    string $subtotal,
    ?string $currency = null,
    OrderStatus $status = OrderStatus::Pending,
    ?Coupon $coupon = null,
    ?string $discountAmount = null,
    ?string $taxAmount = null,
    ?string $shippingAmount = null,
    ?string $referenceId = null,
    ?string $externalId = null,
    ?array $items = null,
    ?array $billingAddress = null,
    ?array $shippingAddress = null,
): Order;

public function markPaid(
    Order|int $order,
    ?string $transactionId = null,
    ?string $referenceId = null,
    ?array $extra = null,
): Order;

public function markCompleted(Order|int $order): Order;
public function cancel(Order|int $order, ?string $reason = null): Order;
public function refund(Order|int $order, ?string $refundAmount = null, ?string $reason = null): Order;
public function applyCoupon(Order|int $order, Coupon|string $coupon): Order;
```

---

### `CouponRepository`

```php
public function validate(Coupon|string $code, Chat|int $user, ?string $subtotal = null): Coupon;
public function useCoupon(Coupon|string $code, Chat|int $user, Order|int $order, ?string $discountApplied = null): CouponUsage;
public function findByCode(string $code): ?Coupon;
public function getUserUsageCount(Coupon|string $code, Chat|int $user): int;
```

---

### `SubscriptionRepository`

```php
public function extend(
    Chat|int $chat,
    string $plan,
    int $durationSeconds,
    ?Order $order = null,
    ?string $source = null,
    ?Chat $createdBy = null,
): Subscription;

public function cancel(Chat|int $chat, string $plan, ?string $reason = null): bool;
public function findActive(Chat|int $chat, string $plan): ?Subscription;
public function isSubscribed(Chat|int $chat, string $plan): bool;
public function getTransactions(Subscription|int $subscription): array;
```

---

### `BroadcastRepository`

```php
public function createCampaign(
    string $text,
    ?array $entities = null,
    ?string $mediaType = null,
    ?string $fileId = null,
    ?array $replyMarkup = null,
    ?array $targetFilter = null,
    ?DateTimeImmutable $scheduledAt = null,
): BroadcastMessage;

public function getNextBatch(BroadcastMessage|int $broadcast, int $batchSize = 100): array;
public function updateProgress(BroadcastMessage|int $broadcast, int $sentIncrement, int $failedIncrement = 0, ?int $lastChatId = null): BroadcastMessage;
public function completeCampaign(BroadcastMessage|int $broadcast): BroadcastMessage;
public function pauseCampaign(BroadcastMessage|int $broadcast): BroadcastMessage;
public function resumeCampaign(BroadcastMessage|int $broadcast): BroadcastMessage;
```

---

### `ReferralRepository`

```php
public function register(
    Chat|int $referrer,
    Chat|int $referred,
    ?string $campaign = null,
    ?array $metadata = null,
): Referral;

public function qualify(Referral|int $referral, ?string $qualificationReason = null): Referral;
public function getReferrer(Chat|int $referred): ?Chat;
public function getReferralsCount(Chat|int $referrer, bool $onlyQualified = false): int;
```

---

### `EntityItemRepository` (CMS)

```php
public function create(
    Chat|int $chat,
    string $slug,
    string $type = 'entity',
    ?string $title = null,
    ?string $content = null,
    string $status = 'draft',
    ?EntityItem $parent = null,
): EntityItem;

public function findBySlug(string $type, string $slug): ?EntityItem;
public function like(EntityItem|int $entity, Chat|int $chat): bool;
public function unlike(EntityItem|int $entity, Chat|int $chat): bool;
public function getLikesCount(EntityItem|int $entity): int;
public function hasLiked(EntityItem|int $entity, Chat|int $chat): bool;
public function rate(EntityItem|int $entity, Chat|int $chat, int $score): EntityRating;
public function getAverageRating(EntityItem|int $entity): float;
public function getRatingsCount(EntityItem|int $entity): int;
```

---

### `MessageRelayRepository`

```php
public function relay(
    Chat|int $chat1,
    int $messageId1,
    Chat|int $chat2,
    int $messageId2,
    ?array $message = null,
): MessageRelay;

public function findOrigin(Chat|int $targetChat, int $targetMessageId): ?MessageRelay;
```

---

### `OptionRepository`

```php
public function get(string $name, mixed $default = null): mixed;
public function set(string $name, mixed $value, ?string $group = null): Option;
public function has(string $name): bool;
public function delete(string $name): bool;
public function getByGroup(string $group): array;
public static function clearRuntimeCache(): void;
```

---

### `CacheRepository` (PSR-16)

```php
public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
public function delete(string $key): bool;
public function clear(): bool;
public function has(string $key): bool;
public function purgeExpired(): int;
```

---

### `DataRepository`

```php
public function get(string $group, string $name, Chat|int|null $chat = null, mixed $default = null): mixed;
public function set(string $group, string $name, mixed $value, Chat|int|null $chat = null): DataItem;
public function has(string $group, string $name, Chat|int|null $chat = null): bool;
public function delete(string $group, string $name, Chat|int|null $chat = null): bool;
```

---

### `TmaSessionRepository`

```php
public function createSession(
    Chat|int $chat,
    int $ttl = 86400,
    ?string $queryId = null,
    ?string $startParam = null,
    ?string $ipAddress = null,
    ?string $userAgent = null,
    ?array $payload = null,
    ?DateTimeImmutable $authDate = null,
): TmaSession;

public function authenticate(string $token, bool $touch = true): ?TmaSession;
public function revoke(string $token): bool;
public function revokeAllForChat(Chat|int $chat): int;
public function getActiveSessionsForChat(Chat|int $chat): array;
public function cleanupExpired(): int;
```

---

### `HistoryRepository`

```php
public function log(
    string $action,
    ?object $entity = null,
    Chat|int|null $chat = null,
    ?array $payload = null,
): History;

public function findTimeline(Chat|int|null $chat = null, ?string $entityType = null, int $limit = 50): array;
```
