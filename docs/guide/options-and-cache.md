# Options, Cache & Data Store

Telegram bots manage three distinct categories of auxiliary state:
1. **Global Bot Options (`OptionRepository`):** Persistent global settings (e.g. welcome messages, maintenance mode, gateway credentials) with automatic in-memory caching.
2. **Temporary Conversation Cache (`CacheRepository`):** Fast database-backed cache with TTL for conversational flows and FSM states (PSR-16).
3. **Scoped Arbitrary & Binary Data (`DataRepository`):** Long-term data storage scoped per user or group, supporting large strings and binary BLOB payloads.

---

## ⚙️ Global Bot Settings (`OptionRepository`)

Inspired by centralized key-value configuration stores, `OptionRepository` manages persistent settings across the bot:

### 1. Reading and Writing Options

```php
// Get option with a fallback default value
$welcomeText = $db->options()->get('welcome_message', 'Welcome to our bot!');

// Set an option with an optional grouping
$db->options()->set('welcome_message', 'Hello and welcome to Tueen Bot!', 'localization');

// Set complex array / JSON settings
$db->options()->set('payment_gateways', [
    'stars_enabled' => true,
    'ton_enabled' => true,
    'crypto_min_deposit' => '5.00',
], 'billing');

// Check existence or remove
if ($db->options()->has('maintenance_mode')) {
    $db->options()->delete('maintenance_mode');
}
```

### 2. Built-in In-Memory Caching
To ensure maximum speed, `OptionRepository` maintains an in-memory process cache. Subsequent reads of the same option during the same request execute in memory with **zero database queries**.

---

## 📦 PSR-16 SimpleCache (`CacheRepository`)

The `CacheRepository` provides database-backed temporary caching implementing PSR-16 `Psr\SimpleCache\CacheInterface`:

```php
$cache = $db->cache();

// 1. Store conversation state with TTL of 1 hour (3600 seconds)
$cache->set('fsm_user_123456', [
    'step' => 'awaiting_payment_amount',
    'selected_currency' => 'TON',
], 3600);

// 2. Retrieve state
$state = $cache->get('fsm_user_123456');
if ($state !== null) {
    echo "Current Step: " . $state['step'] . "\n";
}

// 3. Delete state upon flow completion
$cache->delete('fsm_user_123456');

// 4. Purge expired records (useful in cron jobs or background tasks)
$purgedCount = $db->cache()->purgeExpired();
```

---

## 🗄️ Scoped & Binary Data Store (`DataRepository`)

The **`DataRepository`** (`$db->data()`) handles long-term custom key-value and binary data scoped by a specific chat or group:

```php
// 1. Store user-scoped preferences or documents
$db->data()->set(
    group: 'kyc_verification',
    name: 'passport_scan',
    value: $binaryFileStream, // Stored safely as BLOB
    chat: $userChat,
);

// 2. Retrieve data with fallback
$passportData = $db->data()->get('kyc_verification', 'passport_scan', $userChat);

// 3. Store global namespace data (chat = null)
$db->data()->set(
    group: 'external_sync',
    name: 'last_sync_timestamp',
    value: time(),
);

// 4. Check or delete
if ($db->data()->has('kyc_verification', 'passport_scan', $userChat)) {
    $db->data()->delete('kyc_verification', 'passport_scan', $userChat);
}
```

---

## 🧭 Next Steps

- Audit every action with **[Audit Trails & Histories](/guide/audit-histories)**.
- Accelerate throughput with **[Caching & Performance Tuning](/guide/caching-and-performance)**.
