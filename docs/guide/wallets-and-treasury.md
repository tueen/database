# Wallets & Treasury Ledger

Modern Telegram bots frequently handle digital payments, in-bot credits, Telegram Stars, cryptocurrencies (TON, USDT, BTC), or local fiat currencies.

Tueen Database includes a complete, arbitrary-precision multi-currency ledger with double-entry transaction auditing, payout withdrawals, and balance freezing.

---

## 💰 Arbitrary-Precision Math

All financial amounts are stored as `DECIMAL(36, 18)` (or `40, 18`) and handled as strings in PHP.

::: danger Never Use Floating-Point Math
Floating-point math in PHP (`$amount1 + $amount2`) introduces rounding errors (e.g. `0.1 + 0.2 = 0.30000000000000004`). In financial bot transactions, this can lead to balance discrepancies or exploit vulnerabilities. Tueen Database internally utilizes **BCMath** for all arithmetic.
:::

---

## 💳 Wallet Operations (`WalletRepository`)

The `WalletRepository` provides atomic methods that guarantee balance safety:

### 1. Checking Balances

```php
// Check user's default balance (defaults to XTR - Telegram Stars if omitted)
$starsBalance = $db->wallets()->getBalance($chat);

// Check user's TON balance (defaults to 0.000000000000000000 if not yet initialized)
$tonBalance = $db->wallets()->getBalance($chat, 'TON');

echo "Available: {$starsBalance} XTR\n";
```

::: tip Configurable Default In-System Currency
By default, Tueen Database uses **`XTR`** (the official standard currency code for Telegram Stars) as the default currency across all wallet balances, orders, withdrawals, and treasury records whenever the `$currency` parameter is omitted.

You can customize the default currency globally via `DatabaseConfig` or environment variables:
```php
$config = DatabaseConfig::createSqlite('bot.db')
    ->withDefaultCurrency('COIN'); // Or via TUEEN_DEFAULT_CURRENCY=COIN in .env
```
:::

### 2. Crediting Funds (Deposits, Rewards)

```php
// Credit 25.0 TON to the user
$transaction = $db->wallets()->credit(
    chat: $chat,
    amount: '25.000000000000000000',
    currency: 'TON',
    source: 'stars_conversion',
    sourceId: 98765,
);

// Transaction properties
echo "Transaction ID: {$transaction->publicId}\n";
echo "Balance Before: {$transaction->balanceBefore}\n";
echo "Balance After:  {$transaction->balanceAfter}\n";
```

### 3. Debiting Funds (Purchases, Fees)

```php
use Tueen\Database\Exception\InsufficientBalanceException;

try {
    $transaction = $db->wallets()->debit(
        chat: $chat,
        amount: '10.000000000000000000',
        currency: 'TON',
        source: 'vip_purchase',
    );
} catch (InsufficientBalanceException $e) {
    // Gracefully notify the user that their balance is insufficient
    echo "Error: {$e->getMessage()}\n";
}
```

### 4. Atomic Peer-to-Peer Transfers

Transfer funds between two users in a single atomic database transaction:

```php
$result = $db->wallets()->transfer(
    fromChat: $senderChat,
    toChat: $recipientChat,
    amount: '15.000000000000000000',
    currency: 'TON',
    source: 'p2p_transfer',
);

// Returns both audited transactions
$debitTx = $result['debit'];
$creditTx = $result['credit'];
```

### 5. Balance Freezing & Unfreezing

Hold funds in escrow (e.g. pending P2P escrow, withdrawal requests, or auction bids):

```php
// Freeze funds from available balance into frozen_amount
$db->wallets()->freeze($chat, '50.000000000000000000', 'TON');

// Release frozen funds back to available balance if the escrow is cancelled
$db->wallets()->unfreeze($chat, '50.000000000000000000', 'TON');
```

---

## 💸 Payout Requests & Withdrawals (`WithdrawalRepository`)

When users earn commissions (via affiliate programs, creator rewards, or marketplace sales), they can request withdrawals to their crypto wallets, bank cards, or external accounts.

### 1. Requesting a Withdrawal

When a user submits a withdrawal, `WithdrawalRepository` can automatically freeze the requested amount from their wallet to prevent double-spending:

```php
use Tueen\Database\Enum\WithdrawalStatus;

$withdrawal = $db->withdrawals()->request(
    chat: $userChat,
    amount: '100.000000000000000000',
    currency: 'TON',
    feeAmount: '1.500000000000000000',
    commissionRate: '0.015',
    payoutMethod: 'tonkeeper',
    destination: 'EQBvW8Z5huBkMJYdn3PCDn5B3a4285Um62V975UVqETqNqLk',
    freezeBalance: true, // Automatically freezes 100 TON from available balance
);

echo "Withdrawal Public ID: {$withdrawal->publicId}\n";
echo "Gross Amount:          {$withdrawal->amount} {$withdrawal->currency}\n";
echo "Net To Receive:        {$withdrawal->netIncome} {$withdrawal->currency}\n"; // Computed via property hook
```

### 2. Processing & Approval Workflow

```php
// Admin reviews and approves the withdrawal
$approved = $db->withdrawals()->approve($withdrawal);

// Once the crypto transaction or bank transfer is confirmed:
$completed = $db->withdrawals()->complete(
    withdrawal: $withdrawal,
    txHash: '0x3a89e1b29402c4819e018d9f48c08129d8b746824981',
);

// Or if rejected (automatically unfreezes the held funds back to the user's wallet):
$rejected = $db->withdrawals()->reject(
    withdrawal: $withdrawal,
    reason: 'Invalid recipient wallet address',
);
```

### 3. Lifecycle Statuses (`WithdrawalStatus`)
- **`Pending`:** Newly submitted by the user, awaiting administrator review.
- **`Processing`:** Under automated or manual payout transmission.
- **`Approved`:** Verified and approved for release.
- **`Rejected`:** Denied by administrator (frozen balance automatically restored).
- **`Cancelled`:** Cancelled by user before processing (frozen balance automatically restored).

---

## 🏛️ Bot Treasury (`TreasuryRepository`)

The `Treasury` tracks the bot operator's overall reserve, total commissions earned, and consumed volume per currency:

```php
// Record commission income collected from an order
$tx = $db->treasury()->recordIncome(
    currency: 'TON',
    amount: '2.500000000000000000',
    source: 'order_commission',
);

// Record operational expense (e.g. server hosting or API costs paid from treasury)
$expenseTx = $db->treasury()->recordExpense(
    currency: 'TON',
    amount: '1.000000000000000000',
    source: 'server_hosting',
);

// Check current bot reserve
$reserve = $db->treasury()->getBalance('TON');
```

---

## 🧭 Next Steps

- Process product sales with **[E-Commerce Orders & Billing](/guide/orders-and-coupons)**.
- Manage recurring memberships with **[Subscriptions & VIP](/guide/subscriptions)**.
