<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Database\Entity\BroadcastMessage;
use Tueen\Database\Entity\CacheItem;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Contact;
use Tueen\Database\Entity\Order;
use Tueen\Database\Entity\Subscription;
use Tueen\Database\Entity\WalletBalance;
use Tueen\Database\Entity\Withdrawal;
use Tueen\Database\Enum\BroadcastStatus;
use Tueen\Database\Enum\ChatType;
use Tueen\Database\Enum\OrderStatus;
use Tueen\Database\Enum\TaxMode;

final class EntityHooksTest extends TestCase
{
    public function testChatPropertyHooks(): void
    {
        $chat = new Chat(
            id: 123456789,
            type: ChatType::Private,
            username: '@Elsiom_Dev',
            name: 'Elsiom Dev',
            firstName: 'Elsiom',
            lastName: 'Team',
            isBot: false,
            isPremium: true,
        );

        // Test backed set hook: automatically trims '@' and converts to lowercase
        $this->assertSame('elsiom_dev', $chat->username);
        $this->assertSame('Elsiom', $chat->firstName);
        $this->assertSame('Team', $chat->lastName);
        $this->assertFalse($chat->isBot);
        $this->assertTrue($chat->isPremium);

        // Test empty string / whitespace / lone @ becomes null
        $chat->username = '   ';
        $this->assertNull($chat->username);

        $chat->username = '@';
        $this->assertNull($chat->username);

        $chat->username = '@UPPERCASE_USER';
        $this->assertSame('uppercase_user', $chat->username);

        // Test virtual get hooks
        $this->assertTrue($chat->isPrivate);
        $this->assertFalse($chat->isGroupOrSupergroup);
        $this->assertFalse($chat->isChannel);
        $this->assertSame('Elsiom Dev', $chat->displayName);
        $this->assertTrue($chat->canReceiveMessages);

        $chat->banned = true;
        $this->assertFalse($chat->canReceiveMessages);
    }

    public function testContactFullNameHook(): void
    {
        $contact = new Contact(
            userId: 123,
            phoneNumber: '+123456789',
            firstName: 'John',
            lastName: 'Doe',
            vcard: "BEGIN:VCARD\nVERSION:3.0\nFN:John Doe\nEND:VCARD",
        );
        $this->assertSame('John Doe', $contact->fullName);
        $this->assertSame('123456789', $contact->phoneNumber);
        $this->assertNotNull($contact->vcard);

        $singleName = new Contact(userId: 124, phoneNumber: '987654321', firstName: 'Alice');
        $this->assertSame('Alice', $singleName->fullName);
        $this->assertSame('987654321', $singleName->phoneNumber);
    }

    public function testWalletBalancePositiveAndAvailableHook(): void
    {
        $chat = new Chat(id: 1);
        $wallet = new WalletBalance($chat, 'TON', '12.500000000000000000');
        $this->assertTrue($wallet->hasPositiveBalance);
        $this->assertSame('12.500000000000000000', $wallet->availableBalance);

        // Freeze 2.5 TON
        $wallet->frozenBalance = '2.500000000000000000';
        $this->assertSame('10.000000000000000000', $wallet->availableBalance);

        $wallet->balance = '0.000000000000000000';
        $wallet->frozenBalance = '0.000000000000000000';
        $this->assertFalse($wallet->hasPositiveBalance);
        $this->assertSame('0.000000000000000000', $wallet->availableBalance);
    }

    public function testOrderTotalCalculationHook(): void
    {
        $order = new Order(type: 'vip', subtotalAmount: '100.000000000000000000');
        $order->discountAmount = '15.000000000000000000';
        $order->taxMode = TaxMode::Exclusive;
        $order->taxAmount = '10.000000000000000000';
        $order->shippingAmount = '5.000000000000000000';

        // Total: 100 + 5 (shipping) + 10 (tax) - 15 (discount) = 100
        $this->assertSame('100.000000000000000000', $order->totalAmount);
        $this->assertFalse($order->isPaid);

        $order->status = OrderStatus::Paid;
        $this->assertTrue($order->isPaid);
    }

    public function testWithdrawalNetIncomeHook(): void
    {
        $chat = new Chat(id: 1);
        $withdrawal = new Withdrawal(
            chat: $chat,
            amount: '500.000000000000000000',
            feeAmount: '25.000000000000000000',
        );

        $this->assertSame('475.000000000000000000', $withdrawal->netIncome);
    }

    public function testSubscriptionActiveHook(): void
    {
        $chat = new Chat(id: 1);
        $sub = new Subscription($chat, 'vip', new DateTimeImmutable()->modify('+10 days'));

        $this->assertTrue($sub->isActive);
        $this->assertGreaterThan(0, $sub->remainingSeconds);

        $expiredSub = new Subscription($chat, 'vip', new DateTimeImmutable()->modify('-1 day'));
        $this->assertFalse($expiredSub->isActive);
        $this->assertSame(0, $expiredSub->remainingSeconds);
    }

    public function testBroadcastProgressHook(): void
    {
        $chat = new Chat(id: 1);
        $broadcast = new BroadcastMessage($chat, 1001);
        $broadcast->initialChatCount = 1000;
        $broadcast->sentCount = 450;
        $broadcast->failedCount = 50;
        $broadcast->ignoredCount = 0;

        $this->assertSame(50.0, $broadcast->progressPercentage);
        $this->assertFalse($broadcast->isFinished);

        $broadcast->status = BroadcastStatus::Completed;
        $this->assertTrue($broadcast->isFinished);
    }

    public function testCacheItemExpirationHook(): void
    {
        $live = new CacheItem('test', 'value', ttlSeconds: 3600);
        $this->assertFalse($live->isExpired);

        $expired = new CacheItem('test_old', 'value', ttlSeconds: -10);
        $this->assertTrue($expired->isExpired);
    }

    public function testTmaSessionHooks(): void
    {
        $chat = new Chat(id: 100);
        $token = bin2hex(random_bytes(32));

        $activeSession = new \Tueen\Database\Entity\TmaSession(
            chat: $chat,
            token: $token,
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $this->assertFalse($activeSession->isExpired);
        $this->assertTrue($activeSession->isValid);

        // Revoking marks session invalid
        $activeSession->revoke();
        $this->assertTrue($activeSession->isRevoked);
        $this->assertFalse($activeSession->isValid);

        // Expired session is marked expired and invalid
        $expiredSession = new \Tueen\Database\Entity\TmaSession(
            chat: $chat,
            token: bin2hex(random_bytes(32)),
            expiresAt: new DateTimeImmutable('-1 minute'),
        );

        $this->assertTrue($expiredSession->isExpired);
        $this->assertFalse($expiredSession->isValid);
    }
}
