# Chats & Contacts Management

The **`Chat`** and **`Contact`** entities represent the primary actors in the Telegram bot ecosystem, while **`ChatXp`**, **`ChatLike`**, and **`ChatRating`** power social engagement and gamification.

---

## 💬 The `Chat` Entity

Telegram represents users, basic groups, supergroups, and channels through a single entity: the **Chat**.

### Chat ID Sign Rule
- **Private Users:** Have positive IDs (e.g. `123456789`).
- **Groups, Supergroups & Channels:** Have negative IDs (e.g. `-1001987654321`).

Tueen Database maps `Chat.id` as a signed 64-bit `bigint`, fully accommodating both positive user IDs and negative supergroup/channel IDs.

---

## 🛠️ The `findOrCreate` Pattern

The most frequent operation in any Telegram bot is registering a chat on the first message or update. `ChatRepository::findOrCreate()` handles this atomically:

```php
use Tueen\Database\Enum\ChatType;

$chat = $db->chats()->findOrCreate(
    chatId: $update['message']['chat']['id'],
    username: $update['message']['chat']['username'] ?? null,
    name: $update['message']['chat']['first_name'] ?? 'User',
    type: ChatType::Private,
    languageCode: $update['message']['from']['language_code'] ?? 'en',
);
```

### Automatic Normalization & Touch
- **Username Cleaning:** Leading `@` characters are automatically stripped via PHP 8.4 backed property hooks (`set => ...`).
- **Touch Active Time:** Calling `findOrCreate()` on an existing chat automatically updates `activeAt` and `updatedAt`.
- **Name / Language Synchronization:** If the user changed their Telegram name or language, `findOrCreate()` updates the database record automatically.

---

## 🪝 PHP 8.4 Property Hooks on `Chat`

The `Chat` model includes several convenient virtual property hooks:

```php
// Check chat types directly
if ($chat->isPrivate) {
    // 1-on-1 private conversation
}

if ($chat->isGroupOrSupergroup) {
    // Community group
}

if ($chat->isChannel) {
    // Broadcast channel
}

// Display Name helper
echo "Hello, " . $chat->displayName; // Returns name, or @username, or chat ID

// Message delivery status check
if ($chat->canReceiveMessages) {
    // True only if accessible = true AND banned = false
}
```

---

## 📇 User Contacts & Phone Verification (`ContactRepository`)

The **`Contact`** entity stores verified phone numbers and identity details for Telegram users. 

Contact records can originate from multiple sources:
- **Telegram Native Contact Sharing:** When a user taps a keyboard button requesting contact (`request_contact: true`) or forwards a native contact card (`message.contact`).
- **Telegram Mini Apps (TMA):** Phone numbers requested and verified through WebApp dialogs.
- **Manual Input & OTP Verification:** Numbers entered via conversational bot steps or SMS flows.

### Using `ContactRepository`

```php
// 1. Save or update contact details
$contact = $db->contacts()->saveContact(
    user: $chat,
    phoneNumber: '+1 (987) 654-3210', // Automatically stripped to pure digits: 19876543210
    firstName: 'Alice',
    lastName: 'Smith',
);

echo "Registered: {$contact->fullName} ({$contact->phoneNumber})\n";

// 2. Lookup user by verified phone number
$existing = $db->contacts()->findByPhoneNumber('19876543210');
if ($existing !== null) {
    echo "Found user with chat ID: {$existing->user->id}\n";
}

// 3. Lookup contact for a specific chat
$userContact = $db->contacts()->findByChat($chat);
```

> [!TIP]
> **Automatic Phone Normalization:**
> `Contact::$phoneNumber` utilizes a native PHP 8.4 backed property hook (`set => (string) preg_replace('/\D+/', '', $value)`) that automatically strips non-digit characters (`+`, spaces, dashes, parentheses). This ensures clean, uniform phone numbers for database lookups and SMS gateways.

---

## 🎮 Gamification & Experience Points (`ChatXpRepository`)

Telegram bots frequently reward users with experience points (XP) for sending messages, inviting friends, completing tasks, or participating in quizzes.

Tueen Database provides an atomic XP engine with an immutable transaction audit log:

```php
// 1. Award XP points for an activity
$tx = $db->chatXp()->addXp(
    chat: $chat,
    amount: 100,
    source: 'daily_quest',
    sourceId: 42,
);

echo "Awarded: {$tx->value} XP\n";
echo "XP Before: {$tx->xpBefore} | XP After: {$tx->xpAfter}\n";

// 2. Query current XP
$currentXp = $db->chatXp()->getXp($chat);

// 3. Deduct XP (e.g. penalty or spent points)
$deductTx = $db->chatXp()->deductXp($chat, 25, 'store_redemption');

// 4. Fetch the Community Leaderboard
$topUsers = $db->chatXp()->getLeaderboard(limit: 10);
foreach ($topUsers as $rank => $entry) {
    echo "#" . ($rank + 1) . " {$entry->chat->displayName}: {$entry->xp} XP\n";
}
```

---

## ⭐ Social Feedback (`ChatLike` & `ChatRating`)

Build social and community features (user reputations, admin karma, peer ratings) between chats:

```php
use Tueen\Database\Entity\ChatLike;
use Tueen\Database\Entity\ChatRating;

// 1. User A likes User B's profile
$like = new ChatLike(
    fromChat: $senderChat,
    toChat: $targetChat,
);
$db->getEntityManager()->persist($like);

// 2. User A rates User B (e.g. 95 out of 100)
$rating = new ChatRating(
    fromChat: $senderChat,
    toChat: $targetChat,
    rating: 95,
);
$db->getEntityManager()->persist($rating);

$db->getEntityManager()->flush();
```

---

## 🏷️ Chat Metadata (`ChatMeta`)

Attach arbitrary JSON or typed metadata to any chat (settings, onboarding progress, preferences):

```php
use Tueen\Database\Entity\ChatMeta;

$meta = new ChatMeta(
    chat: $chat,
    metaKey: 'notification_settings',
    value: ['sound' => true, 'frequency' => 'daily'],
    group: 'preferences',
);

$db->getEntityManager()->persist($meta);
$db->getEntityManager()->flush();
```

---

## 🧭 Next Steps

- Manage bot finances with **[Wallets & Treasury Ledger](/guide/wallets-and-treasury)**.
- Implement **[E-Commerce Orders & Billing](/guide/orders-and-coupons)**.
- Connect Mini Apps with **[Mini App Sessions & Auth](/guide/mini-app-sessions)**.
