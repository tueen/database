# Enums & Value Objects Reference

Tueen Database utilizes native backed string enums for type safety, validation, and database storage.

---

## 🏛️ Enum Catalog

### 1. `ChatType` (`Tueen\Database\Enum\ChatType`)

Represents the standard Telegram chat types:

```php
enum ChatType: string
{
    case Private = 'private';
    case Group = 'group';
    case Supergroup = 'supergroup';
    case Channel = 'channel';
}
```

#### Helper Methods:
- `$type->isDirect()`: Returns `true` if `Private`.
- `$type->isGroup()`: Returns `true` if `Group` or `Supergroup`.
- `$type->isChannel()`: Returns `true` if `Channel`.

---

### 2. `OrderStatus` (`Tueen\Database\Enum\OrderStatus`)

Represents e-commerce order statuses:

```php
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
```

#### Helper Methods:
- `$status->isSuccessful()`: Returns `true` for `Paid` or `Completed`.
- `$status->isFinal()`: Returns `true` for `Completed`, `Cancelled`, `Refunded`, or `Failed`.

---

### 3. `BroadcastStatus` (`Tueen\Database\Enum\BroadcastStatus`)

Represents mass messaging campaign states:

```php
enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
```

#### Helper Methods:
- `$status->isRunning()`: Returns `true` for `Queued` or `Processing`.
- `$status->isTerminal()`: Returns `true` for `Completed`, `Cancelled`, or `Failed`.

---

### 4. `CouponType` & `CouponStatus`

```php
enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}

enum CouponStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
}
```

---

### 5. `TaxMode` (`Tueen\Database\Enum\TaxMode`)

```php
enum TaxMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
    case Exempt = 'exempt';
    case ReverseCharge = 'reverse_charge';
}
```

---

### 6. `ReferralStatus` (`Tueen\Database\Enum\ReferralStatus`)

```php
enum ReferralStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
}
```

---

### 7. `WithdrawalStatus` (`Tueen\Database\Enum\WithdrawalStatus`)

```php
enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
```
