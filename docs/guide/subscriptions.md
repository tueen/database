# Subscriptions & VIP Access

Many Telegram bots offer premium memberships, private channel access, or tiered VIP features based on time-limited memberships.

Tueen Database provides a dedicated subscription manager that handles expiration calculations and renewal auditing.

---

## 💎 The `Subscription` Model

A subscription binds a `Chat` to a tier `type` (e.g. `vip`, `channel_pro`, `unlimited_ai`):

- **`expiresAt`:** A `DateTimeImmutable` representing the moment access lapses.
- **`totalAddedSeconds`:** Cumulative seconds ever credited to this subscription.
- **Property Hooks:**
  - `$sub->isActive`: Returns `true` if `expiresAt > now`.
  - `$sub->remainingSeconds`: Returns remaining time in seconds (0 if expired).

---

## 🛠️ Managing Subscriptions

### 1. Checking Active Status

```php
// Check if user currently has an active 'vip' subscription
if ($db->subscriptions()->hasActive($chat, 'vip')) {
    $sub = $db->subscriptions()->getActive($chat, 'vip');
    echo "VIP active! Remaining: " . ($sub->remainingSeconds / 86400) . " days.\n";
} else {
    echo "No active VIP subscription.\n";
}
```

### 2. Granting or Extending Time

When a user purchases 30 days of VIP access:

```php
// 30 days = 30 * 24 * 3600 = 2,592,000 seconds
$sub = $db->subscriptions()->addTime(
    chat: $chat,
    seconds: 2592000,
    type: 'vip',
    source: 'order_payment',
    sourceId: $order->id,
);

echo "New Expiration: " . $sub->expiresAt->format('Y-m-d H:i:s') . "\n";
```

::: tip Seamless Extension
If the user already has an active subscription with 5 days remaining, `addTime()` appends the new 30 days to their existing expiration date (resulting in 35 days total), rather than resetting it from today.
:::

---

## 📜 Subscription Audit Trail (`SubscriptionTransaction`)

Every time a subscription is created, renewed, extended, or expired, an immutable `SubscriptionTransaction` is automatically recorded with:
- `expiresAtBefore` and `expiresAtAfter`
- `totalAddedSecondsBefore` and `totalAddedSecondsAfter`
- `source` (e.g. `promo_gift`, `stripe`, `telegram_stars`)
- `createdBy` (administrator or bot account)

---

## 🧭 Next Steps

- Engage your users using the **[Broadcast Campaign Engine](/guide/broadcast-engine)**.
- Explore **[Content Entities & CMS](/guide/content-entities)**.
