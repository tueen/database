<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use LogicException;
use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Enum\WithdrawalStatus;
use Tueen\Database\Exception\InsufficientBalanceException;

final class ExtendedFeaturesTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testWithdrawalLifecycleWithWalletFreeze(): void
    {
        $chat = $this->db->chats()->findOrCreate(9001, 'withdrawer');
        $this->db->wallets()->credit($chat, '100.000000000000000000', 'TON');

        // 1. Request withdrawal of 40 TON with 2 TON fee
        $withdrawal = $this->db->withdrawals()->request(
            chat: $chat,
            amount: '40.000000000000000000',
            feeAmount: '2.000000000000000000',
            currency: 'TON',
            destination: 'UQ_TEST_WALLET_ADDRESS',
        );

        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame('38.000000000000000000', $withdrawal->netIncome);

        // Check wallet balance: 40 TON must be frozen, 60 TON available
        $wallet = $this->db->wallets()->getWallet($chat, 'TON');
        $this->assertNotNull($wallet);
        $this->assertSame('100.000000000000000000', $wallet->balance);
        $this->assertSame('40.000000000000000000', $wallet->frozenBalance);
        $this->assertSame('60.000000000000000000', $wallet->availableBalance);

        // 2. Approve withdrawal
        $this->db->withdrawals()->approve($withdrawal, transactionId: 'tx_blockchain_hash_123');
        $this->assertSame(WithdrawalStatus::Approved, $withdrawal->status);
        $this->assertSame('tx_blockchain_hash_123', $withdrawal->transactionId);
        $this->assertNotNull($withdrawal->walletTransaction);

        // Wallet balance must now be 60 TON total, 0 frozen
        $this->db->getEntityManager()->refresh($wallet);
        $this->assertSame('60.000000000000000000', $wallet->balance);
        $this->assertSame('0.000000000000000000', $wallet->frozenBalance);
        $this->assertSame('60.000000000000000000', $wallet->availableBalance);

        // Cannot approve again
        $this->expectException(LogicException::class);
        $this->db->withdrawals()->approve($withdrawal);
    }

    public function testWithdrawalRejectionUnfreezesBalance(): void
    {
        $chat = $this->db->chats()->findOrCreate(9002, 'rejecter');
        $this->db->wallets()->credit($chat, '50.000000000000000000', 'TON');

        // Request 30 TON
        $withdrawal = $this->db->withdrawals()->request(
            chat: $chat,
            amount: '30.000000000000000000',
            currency: 'TON',
        );

        $wallet = $this->db->wallets()->getWallet($chat, 'TON');
        $this->assertNotNull($wallet);
        $this->assertSame('30.000000000000000000', $wallet->frozenBalance);

        // Reject withdrawal
        $this->db->withdrawals()->reject($withdrawal, reason: 'Invalid payout address');
        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->status);

        $this->db->getEntityManager()->refresh($wallet);
        $this->assertSame('50.000000000000000000', $wallet->balance);
        $this->assertSame('0.000000000000000000', $wallet->frozenBalance);
        $this->assertSame('50.000000000000000000', $wallet->availableBalance);
    }

    public function testTreasuryExpenseLockingAndValidation(): void
    {
        // 1. Record treasury income
        $this->db->treasuries()->recordIncome('USDT', '500.000000000000000000', 'investor_funding');
        $this->assertSame('500.000000000000000000', $this->db->treasuries()->getBalance('USDT'));

        // 2. Record valid expense
        $this->db->treasuries()->recordExpense('USDT', '200.000000000000000000', 'server_cost');
        $this->assertSame('300.000000000000000000', $this->db->treasuries()->getBalance('USDT'));

        // 3. Record expense exceeding balance
        $this->expectException(InsufficientBalanceException::class);
        $this->db->treasuries()->recordExpense('USDT', '400.000000000000000000', 'marketing');
    }

    public function testDataRepositoryIsolationAndOperations(): void
    {
        $chat1 = $this->db->chats()->findOrCreate(9101, 'data_user_1');
        $chat2 = $this->db->chats()->findOrCreate(9102, 'data_user_2');

        // Global data (chat = null)
        $this->db->data()->set('settings', 'app_title', 'Tueen Bot Engine');

        // Chat 1 data with same group and name
        $this->db->data()->set('settings', 'app_title', 'User 1 Custom Title', $chat1);

        // Chat 2 data
        $this->db->data()->set('settings', 'app_title', 'User 2 Custom Title', $chat2);

        // Verify isolation
        $this->assertSame('Tueen Bot Engine', $this->db->data()->get('settings', 'app_title'));
        $this->assertSame('User 1 Custom Title', $this->db->data()->get('settings', 'app_title', $chat1));
        $this->assertSame('User 2 Custom Title', $this->db->data()->get('settings', 'app_title', $chat2));

        // Check has and delete
        $this->assertTrue($this->db->data()->has('settings', 'app_title', $chat1));
        $this->assertTrue($this->db->data()->delete('settings', 'app_title', $chat1));
        $this->assertFalse($this->db->data()->has('settings', 'app_title', $chat1));
        $this->assertNull($this->db->data()->get('settings', 'app_title', $chat1));

        // Other chats remain untouched
        $this->assertSame('Tueen Bot Engine', $this->db->data()->get('settings', 'app_title'));
        $this->assertSame('User 2 Custom Title', $this->db->data()->get('settings', 'app_title', $chat2));

        // Test getByGroup
        $this->db->data()->set('settings', 'theme', 'dark', $chat2);
        $groupItems = $this->db->data()->getByGroup('settings', $chat2);
        $this->assertCount(2, $groupItems);
    }

    public function testContactRepositoryOperations(): void
    {
        $chat = $this->db->chats()->findOrCreate(9201, 'contact_tester');

        $contact = $this->db->contacts()->saveContact(
            user: $chat,
            phoneNumber: '+1 (555) 123-4567',
            firstName: 'Sarah',
            lastName: 'Connor',
            vcard: 'BEGIN:VCARD...',
        );

        $this->assertSame(9201, $contact->userId);
        $this->assertSame('15551234567', $contact->phoneNumber);
        $this->assertSame('Sarah Connor', $contact->fullName);
        $this->assertSame('BEGIN:VCARD...', $contact->vcard);

        $byPhone = $this->db->contacts()->findByPhoneNumber('+1 (555) 123-4567');
        $this->assertNotNull($byPhone);
        $this->assertSame(9201, $byPhone->userId);

        $byChat = $this->db->contacts()->findByChat($chat);
        $this->assertNotNull($byChat);
        $this->assertSame('15551234567', $byChat->phoneNumber);
    }

    public function testChatXpRepositoryOperations(): void
    {
        $chatA = $this->db->chats()->findOrCreate(9301, 'xp_user_a');
        $chatB = $this->db->chats()->findOrCreate(9302, 'xp_user_b');

        $this->assertSame(0, $this->db->chatXp()->getXp($chatA));

        $this->db->chatXp()->addXp($chatA, 50);
        $this->db->chatXp()->addXp($chatB, 120);
        $this->db->chatXp()->addXp($chatA, 30); // 80 total

        $this->assertSame(80, $this->db->chatXp()->getXp($chatA));
        $this->assertSame(120, $this->db->chatXp()->getXp($chatB));

        // Leaderboard: chatB first (120), then chatA (80)
        $leaderboard = $this->db->chatXp()->getLeaderboard(10);
        $this->assertCount(2, $leaderboard);
        $this->assertSame(9302, $leaderboard[0]->chat->id);
        $this->assertSame(120, $leaderboard[0]->xp);
        $this->assertSame(9301, $leaderboard[1]->chat->id);
        $this->assertSame(80, $leaderboard[1]->xp);

        // Set XP
        $this->db->chatXp()->setXp($chatA, 200);
        $this->assertSame(200, $this->db->chatXp()->getXp($chatA));
    }

    public function testEntityItemLikesAndRatings(): void
    {
        $creator = $this->db->chats()->findOrCreate(9401, 'creator');
        $voter1 = $this->db->chats()->findOrCreate(9402, 'voter1');
        $voter2 = $this->db->chats()->findOrCreate(9403, 'voter2');

        $item = $this->db->entities()->create(
            chat: $creator,
            slug: 'item-alpha',
            type: 'catalog',
            title: 'Item Alpha',
            content: 'Description of item',
        );

        $this->assertSame('catalog', $item->type);
        $this->assertSame('Item Alpha', $item->title);
        $this->assertSame(0, $this->db->entities()->getLikesCount($item));

        // Like
        $this->db->entities()->like($item, $voter1);
        $this->db->entities()->like($item, $voter2);
        $this->assertSame(2, $this->db->entities()->getLikesCount($item));
        $this->assertTrue($this->db->entities()->hasLiked($item, $voter1));

        // Unlike
        $this->assertTrue($this->db->entities()->unlike($item, $voter1));
        $this->assertSame(1, $this->db->entities()->getLikesCount($item));
        $this->assertFalse($this->db->entities()->hasLiked($item, $voter1));

        // Rate
        $this->db->entities()->rate($item, $voter1, 40);
        $this->db->entities()->rate($item, $voter2, 50);
        $this->assertEqualsWithDelta(45.0, $this->db->entities()->getAverageRating($item), 0.01);
    }

    public function testMessageRelayOperations(): void
    {
        $userChat = $this->db->chats()->findOrCreate(9501, 'customer');
        $adminChat = $this->db->chats()->findOrCreate(9502, 'support_agent');

        $relay = $this->db->messageRelays()->relay(
            chat1: $userChat,
            messageId1: 1001,
            chat2: $adminChat,
            messageId2: 2002,
        );

        $this->assertSame(9501, $relay->chat1->id);
        $this->assertSame(1001, $relay->messageId1);
        $this->assertSame(9502, $relay->chat2->id);
        $this->assertSame(2002, $relay->messageId2);

        // Find origin from admin's perspective
        $foundOrigin = $this->db->messageRelays()->findOrigin($adminChat, 2002);
        $this->assertNotNull($foundOrigin);
        $this->assertSame(9501, $foundOrigin->chat1->id);
        $this->assertSame(1001, $foundOrigin->messageId1);

        // Find forward from user's perspective
        $foundForward = $this->db->messageRelays()->findForward($userChat, 1001);
        $this->assertNotNull($foundForward);
        $this->assertSame(9502, $foundForward->chat2->id);
        $this->assertSame(2002, $foundForward->messageId2);
    }
}
