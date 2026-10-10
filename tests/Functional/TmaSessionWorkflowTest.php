<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Entity\TmaSession;

final class TmaSessionWorkflowTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testCreateAndAuthenticateSession(): void
    {
        $chat = $this->db->chats()->findOrCreate(777, 'tma_gamer', 'Alex');

        $session = $this->db->tmaSessions()->createSession(
            chat: $chat,
            ttl: 3600,
            queryId: 'AAG7zM0FAAAAALvMzQU',
            startParam: 'ref_telegram_star',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 (Telegram Mini App)',
            payload: ['level' => 5, 'coins' => 1250],
            authDate: new DateTimeImmutable('-10 seconds'),
        );

        $this->assertInstanceOf(TmaSession::class, $session);
        $this->assertSame(777, $session->chat->id);
        $this->assertSame('AAG7zM0FAAAAALvMzQU', $session->queryId);
        $this->assertSame('ref_telegram_star', $session->startParam);
        $this->assertSame('192.168.1.100', $session->ipAddress);
        $this->assertSame(['level' => 5, 'coins' => 1250], $session->payload);
        $this->assertTrue($session->isValid);
        $this->assertFalse($session->isExpired);
        $this->assertFalse($session->isRevoked);

        // Authenticate with valid token
        $authenticated = $this->db->tmaSessions()->authenticate($session->token);
        $this->assertNotNull($authenticated);
        $this->assertSame($session->id, $authenticated->id);
        $this->assertSame($session->token, $authenticated->token);
    }

    public function testInvalidAndExpiredTokens(): void
    {
        // Non-existent token returns null
        $this->assertNull($this->db->tmaSessions()->authenticate('non_existent_token_123456'));

        $chat = $this->db->chats()->findOrCreate(888, 'expired_tma_user');

        // Create expired session
        $expired = $this->db->tmaSessions()->createSession(
            chat: $chat,
            ttl: -10, // already expired
        );

        $this->assertTrue($expired->isExpired);
        $this->assertNull($this->db->tmaSessions()->authenticate($expired->token));
    }

    public function testRevokeSession(): void
    {
        $chat = $this->db->chats()->findOrCreate(999, 'revokable_user');
        $session = $this->db->tmaSessions()->createSession($chat, ttl: 7200);

        $this->assertTrue($session->isValid);

        // Revoke the token
        $revoked = $this->db->tmaSessions()->revoke($session->token);
        $this->assertTrue($revoked);

        // Subsequent authentication attempts must fail
        $this->assertNull($this->db->tmaSessions()->authenticate($session->token));

        // Revoking non-existent token returns false
        $this->assertFalse($this->db->tmaSessions()->revoke('unknown_token'));
    }

    public function testRevokeAllForChatAndActiveListing(): void
    {
        $chat = $this->db->chats()->findOrCreate(1111, 'multi_device_user');

        $s1 = $this->db->tmaSessions()->createSession($chat, ttl: 3600, userAgent: 'iPhone 15');
        $s2 = $this->db->tmaSessions()->createSession($chat, ttl: 3600, userAgent: 'Desktop Chrome');

        $active = $this->db->tmaSessions()->getActiveSessionsForChat($chat);
        $this->assertCount(2, $active);

        // Revoke all sessions for this chat
        $count = $this->db->tmaSessions()->revokeAllForChat($chat);
        $this->assertSame(2, $count);

        $this->assertNull($this->db->tmaSessions()->authenticate($s1->token));
        $this->assertNull($this->db->tmaSessions()->authenticate($s2->token));

        $activeAfter = $this->db->tmaSessions()->getActiveSessionsForChat($chat);
        $this->assertCount(0, $activeAfter);
    }

    public function testCleanupExpiredSessions(): void
    {
        $chat = $this->db->chats()->findOrCreate(2222, 'cleanable_user');

        $this->db->tmaSessions()->createSession($chat, ttl: -100);
        $this->db->tmaSessions()->createSession($chat, ttl: -50);
        $this->db->tmaSessions()->createSession($chat, ttl: 3600); // live session

        $deletedCount = $this->db->tmaSessions()->cleanupExpired();
        $this->assertSame(2, $deletedCount);

        // The live session still exists
        $active = $this->db->tmaSessions()->getActiveSessionsForChat($chat);
        $this->assertCount(1, $active);
    }
}
