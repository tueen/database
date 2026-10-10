<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Exception\InsufficientBalanceException;

final class WalletWorkflowTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testCreditAndDebit(): void
    {
        $chat = $this->db->chats()->findOrCreate(5001, 'wallet_user');

        // Initial balance should be 0
        $this->assertSame('0.000000000000000000', $this->db->wallets()->getBalance($chat, 'TON'));

        // Credit 10.5 TON
        $tx1 = $this->db->wallets()->credit($chat, '10.500000000000000000', 'TON', 'deposit');
        $this->assertTrue($tx1->isCredit);
        $this->assertSame('10.500000000000000000', $this->db->wallets()->getBalance($chat, 'TON'));

        // Debit 4.0 TON
        $tx2 = $this->db->wallets()->debit($chat, '4.000000000000000000', 'TON', 'order_payment');
        $this->assertTrue($tx2->isDebit);
        $this->assertSame('6.500000000000000000', $this->db->wallets()->getBalance($chat, 'TON'));
    }

    public function testInsufficientBalanceThrowsException(): void
    {
        $chat = $this->db->chats()->findOrCreate(5002, 'broke_user');
        $this->db->wallets()->credit($chat, '2.000000000000000000', 'USDT');

        $this->expectException(InsufficientBalanceException::class);
        $this->db->wallets()->debit($chat, '5.000000000000000000', 'USDT');
    }

    public function testAtomicTransferWithDefaultCurrency(): void
    {
        $alice = $this->db->chats()->findOrCreate(6001, 'alice');
        $bob = $this->db->chats()->findOrCreate(6002, 'bob');

        // Testing default currency fallback (XTR)
        $this->db->wallets()->credit($alice, '100.000000000000000000');

        $result = $this->db->wallets()->transfer($alice, $bob, '35.000000000000000000');

        $this->assertSame('65.000000000000000000', $this->db->wallets()->getBalance($alice));
        $this->assertSame('35.000000000000000000', $this->db->wallets()->getBalance($bob));
        $this->assertSame('65.000000000000000000', $this->db->wallets()->getBalance($alice, 'XTR'));
        $this->assertSame('35.000000000000000000', $this->db->wallets()->getBalance($bob, 'XTR'));
        $this->assertTrue($result['debit']->isDebit);
        $this->assertTrue($result['credit']->isCredit);
    }

    public function testFreezeAndUnfreezeLifecycle(): void
    {
        $chat = $this->db->chats()->findOrCreate(6003, 'freezer');
        $this->db->wallets()->credit($chat, '100.000000000000000000', 'TON');

        // Freeze 40 TON
        $wallet = $this->db->wallets()->freeze($chat, '40.000000000000000000', 'TON');
        $this->assertSame('100.000000000000000000', $wallet->balance);
        $this->assertSame('40.000000000000000000', $wallet->frozenBalance);
        $this->assertSame('60.000000000000000000', $wallet->availableBalance);

        // Attempting to debit more than available (60 TON) must fail even though total balance is 100 TON
        try {
            $this->db->wallets()->debit($chat, '70.000000000000000000', 'TON');
            $this->fail('Expected InsufficientBalanceException was not thrown');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame('60.000000000000000000', $e->balance);
            $this->assertSame('70.000000000000000000', $e->required);
        }

        // Unfreeze 15 TON
        $wallet = $this->db->wallets()->unfreeze($chat, '15.000000000000000000', 'TON');
        $this->assertSame('25.000000000000000000', $wallet->frozenBalance);
        $this->assertSame('75.000000000000000000', $wallet->availableBalance);

        // Debit the remaining frozen 25 TON (e.g. payout execution)
        $tx = $this->db->wallets()->debitFrozen($chat, '25.000000000000000000', 'TON', 'withdrawal_complete');
        $this->assertTrue($tx->isDebit);
        $this->assertSame('-25.000000000000000000', $tx->amount);

        // Verify wallet final state
        $walletFinal = $this->db->wallets()->getWallet($chat, 'TON');
        $this->assertNotNull($walletFinal);
        $this->assertSame('75.000000000000000000', $walletFinal->balance);
        $this->assertSame('0.000000000000000000', $walletFinal->frozenBalance);
        $this->assertSame('75.000000000000000000', $walletFinal->availableBalance);
    }
}
