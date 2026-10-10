# Audit Trails & Histories

In multi-user bots and financial systems, maintaining a verifiable audit trail of every administrative action, balance adjustment, and state transition is essential.

Tueen Database includes a universal event log via the **`History`** entity.

---

## 📜 The `History` Model

The `History` entity records any change across any domain model:

- **`targetType` & `targetId`:** Polymorphic target (e.g. `order` #5, `chat` #123456, `entity` #42).
- **`chatId`:** The actor or subject who triggered or experienced the event.
- **`action`:** Verb string (e.g. `status_change`, `balance_override`, `ban_user`, `refund`).
- **`oldValue` & `newValue`:** Previous vs updated state.
- **`oldStatus` & `newStatus`:** Status delta.
- **`requestId`:** Telegram update ID or API request UUID for end-to-end tracing.
- **`extra`:** Free-form JSON context.

---

## 🛠️ Logging Audit Events

Use `HistoryRepository::log()` to record events seamlessly:

```php
$db->histories()->log(
    targetType: 'order',
    targetId: $order->id,
    action: 'status_transition',
    chatId: $adminUser->id,
    oldStatus: 'pending',
    newStatus: 'paid',
    requestId: 'tg_update_98712398',
    extra: [
        'gateway' => 'telegram_stars',
        'ip' => '10.0.0.4',
    ],
);
```

### Retrieving Timeline History

```php
// Retrieve the complete chronological history of an order
$timeline = $db->histories()->getTimeline(targetType: 'order', targetId: 5);

foreach ($timeline as $entry) {
    echo "{$entry->createdAt->format('H:i:s')} - {$entry->action}: {$entry->oldStatus} -> {$entry->newStatus}\n";
}
```

---

## 🧭 Next Steps

- Optimize throughput with **[Caching & Performance Tuning](/guide/caching-and-performance)**.
- Explore CLI administration in **[CLI Tooling](/guide/cli-tooling)**.
