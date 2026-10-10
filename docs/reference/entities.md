# Entity Models Catalog

Tueen Database provides 22+ specialized domain models optimized for Telegram bots.

---

## 🏛️ Entity & Table Summary

| Entity Class | Table Name | Purpose |
| :--- | :--- | :--- |
| `Chat` | `chats` | Core Telegram chat, user, group, supergroup, or channel record |
| `Contact` | `contacts` | Telegram shared contact cards (phone, first name, last name) |
| `ChatMeta` | `chat_meta` | Key-value settings and metadata per chat |
| `ChatLike` | `chat_likes` | Social like relationships between chats |
| `ChatRating` | `chat_ratings` | Numerical ratings (0-100) between chats |
| `ChatXp` | `chat_xp` | Gamification experience points per chat |
| `ChatXpTransaction` | `chat_xp_transactions` | Immutable audit log of XP adjustments |
| `WalletBalance` | `wallet_balances` | Multi-currency balance ledger (TON, Stars, USDT, fiat) |
| `WalletTransaction` | `wallet_transactions` | Double-entry transaction ledger with audit trail |
| `Treasury` | `treasury` | Bot operator's reserve balance and total commission |
| `TreasuryTransaction`| `treasury_transactions`| Audit log of treasury movements |
| `Coupon` | `coupons` | Discount voucher codes (percentage and fixed) |
| `CouponMeta` | `coupon_meta` | Key-value metadata per coupon |
| `CouponUsage` | `coupon_usages` | Redemption tracking per user and order |
| `Order` | `orders` | Billing orders, invoice tracking, tax, and delivery |
| `OrderMeta` | `order_meta` | Key-value metadata per order |
| `Withdrawal` | `withdrawals` | Payout requests to crypto wallets, cards, or bank accounts |
| `WithdrawalMeta` | `withdrawal_meta` | Key-value metadata per withdrawal |
| `Subscription` | `subscriptions` | Time-limited memberships and VIP privileges |
| `SubscriptionTransaction` | `subscription_transactions` | Audit log of subscription extensions and renewals |
| `Referral` | `referrals` | Viral growth and affiliate tracking with self-referral prevention |
| `BroadcastMessage` | `broadcast_messages` | Chunked mass messaging campaigns with offset cursor |
| `EntityItem` | `entities` | Generic CMS content catalog (products, tickets, articles) |
| `EntityMeta` | `entity_meta` | Key-value metadata per content entity |
| `EntityLike` | `entity_likes` | Social likes on content entities |
| `EntityRating` | `entity_ratings` | Numerical ratings on content entities |
| `MessageRelay` | `message_relays` | Cross-chat message bridging and ticketing relays |
| `Option` | `options` | Global bot settings and configuration store |
| `DataItem` | `data` | Binary blob storage and persistent sessions |
| `CacheItem` | `cache` | Database-backed PSR-16 cache store with TTL |
| `TmaSession` | `tma_sessions` | Telegram Mini App (TMA) authentication and bearer sessions |
| `History` | `histories` | Universal polymorphic audit trail for any model action |
| `SchemaVersion` | `schema_versions` | Applied schema versions and migration tracking |

---

## 🪝 PHP 8.4 Property Hooks Reference

| Entity | Property Hook | Type | Description |
| :--- | :--- | :--- | :--- |
| `Chat` | `$isPrivate` | `bool` | True if type is `private` (1-on-1 user) |
| `Chat` | `$isGroupOrSupergroup`| `bool` | True if type is `group` or `supergroup` |
| `Chat` | `$isChannel` | `bool` | True if type is `channel` |
| `Chat` | `$displayName` | `string` | Resolved name, `@username`, or string chat ID |
| `Chat` | `$canReceiveMessages` | `bool` | True if accessible and not banned |
| `Chat` | `$username` (set hook)| `?string` | Automatically normalizes and strips leading `@` |
| `Contact` | `$fullName` | `string` | Formatted `Firstname Lastname` |
| `Contact` | `$phoneNumber` (set hook) | `string` | Automatically normalizes and strips leading `+` |
| `WalletBalance`| `$hasPositiveBalance`| `bool` | True if balance > 0 |
| `WalletTransaction` | `$isCredit` | `bool` | True if amount > 0 |
| `WalletTransaction` | `$isDebit` | `bool` | True if amount < 0 |
| `Coupon` | `$isValidNow` | `bool` | True if active and within start/end dates |
| `Order` | `$isPaid` | `bool` | True if status is Paid or Completed |
| `Order` | `$totalAmount` | `string` | Computed subtotal + tax + shipping - discount |
| `Withdrawal` | `$netIncome` | `string` | Computed gross amount - fee |
| `Subscription` | `$isActive` | `bool` | True if `expiresAt > now` |
| `Subscription` | `$remainingSeconds`| `int` | Remaining seconds until expiration |
| `BroadcastMessage` | `$progressPercentage` | `float` | Computed delivery percentage (0.0 to 100.0) |
| `BroadcastMessage` | `$isFinished` | `bool` | True if broadcast is completed, cancelled, or failed |
| `CacheItem` | `$isExpired` | `bool` | True if expiration time has passed |
| `Referral` | `$isQualified` | `bool` | True if referral status is qualified |
| `TmaSession` | `$isExpired` | `bool` | True if session expiresAt has passed |
| `TmaSession` | `$isValid` | `bool` | True if not revoked and not expired |
