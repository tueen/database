<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Entity\BroadcastMessage;
use Tueen\Database\Enum\BroadcastStatus;
use Tueen\Database\Enum\ChatType;

final class SubscriptionAndBroadcastTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testSubscriptionTimeExtension(): void
    {
        $chat = $this->db->chats()->findOrCreate(8001, 'subscriber');

        $this->assertFalse($this->db->subscriptions()->hasActive($chat, 'vip'));

        // Add 30 days (2,592,000 seconds)
        $sub = $this->db->subscriptions()->addTime($chat, 2592000, 'vip', 'purchase');

        $this->assertTrue($sub->isActive);
        $this->assertSame(2592000, $sub->totalAddedSeconds);
        $this->assertTrue($this->db->subscriptions()->hasActive($chat, 'vip'));

        // Add 7 more days (604,800 seconds)
        $subUpdated = $this->db->subscriptions()->addTime($chat, 604800, 'vip', 'bonus');
        $this->assertSame(2592000 + 604800, $subUpdated->totalAddedSeconds);
    }

    public function testBroadcastBatchFetchingAndProgress(): void
    {
        // Populate 5 chats
        $c1 = $this->db->chats()->findOrCreate(101, 'c1', type: ChatType::Private);
        $c2 = $this->db->chats()->findOrCreate(102, 'c2', type: ChatType::Private);
        $c3 = $this->db->chats()->findOrCreate(103, 'c3', type: ChatType::Group);
        $c4 = $this->db->chats()->findOrCreate(104, 'c4', type: ChatType::Private);
        $c5 = $this->db->chats()->findOrCreate(105, 'c5', type: ChatType::Private);

        $admin = $this->db->chats()->findOrCreate(1, 'admin');

        $broadcast = new BroadcastMessage(
            sourceChat: $admin,
            sourceMessageId: 999,
            status: BroadcastStatus::Processing,
            allowPrivateChats: true,
            allowGroupChats: false,
        );
        $this->db->getEntityManager()->persist($broadcast);
        $this->db->getEntityManager()->flush();

        $targetCount = $this->db->broadcasts()->countTargetChats($broadcast);
        // Chats matching private: 1, 101, 102, 104, 105 = 5 chats
        $this->assertSame(5, $targetCount);
        $broadcast->initialChatCount = $targetCount;
        $this->db->getEntityManager()->flush();

        // Fetch batch of 2
        $batch1 = $this->db->broadcasts()->fetchNextPendingBatch($broadcast, batchSize: 2);
        $this->assertCount(2, $batch1);
        $this->assertSame(1, $batch1[0]->id);
        $this->assertSame(101, $batch1[1]->id);

        // Update progress
        $this->db->broadcasts()->updateProgress($broadcast, lastChatId: 101, sentIncrement: 2);
        $this->assertSame(101, $broadcast->lastOffset);
        $this->assertSame(2, $broadcast->sentCount);

        // Fetch next batch
        $batch2 = $this->db->broadcasts()->fetchNextPendingBatch($broadcast, batchSize: 2);
        $this->assertCount(2, $batch2);
        $this->assertSame(102, $batch2[0]->id);
        $this->assertSame(104, $batch2[1]->id);
    }

    public function testBroadcastWithNegativeTelegramChatIds(): void
    {
        // Negative Telegram Chat IDs for channels & supergroups
        $channel = $this->db->chats()->findOrCreate(-1001987654321, 'my_channel', type: ChatType::Channel);
        $supergroup = $this->db->chats()->findOrCreate(-1001122334455, 'my_group', type: ChatType::Supergroup);
        $privateUser = $this->db->chats()->findOrCreate(999888, 'john_doe', type: ChatType::Private);

        $admin = $this->db->chats()->findOrCreate(1, 'admin');

        $broadcast = new BroadcastMessage(
            sourceChat: $admin,
            sourceMessageId: 100,
            status: BroadcastStatus::Processing,
            allowPrivateChats: true,
            allowGroupChats: true,
            allowSupergroupChats: true,
            allowChannelChats: true,
        );
        $this->db->getEntityManager()->persist($broadcast);
        $this->db->getEntityManager()->flush();

        // The broadcast starts with lastOffset = null
        $this->assertNull($broadcast->lastOffset);

        // Fetch first batch of size 2. Because order is c.id ASC, the negative IDs should be returned first!
        $batch = $this->db->broadcasts()->fetchNextPendingBatch($broadcast, batchSize: 2);
        $this->assertCount(2, $batch);
        $this->assertSame(-1001987654321, $batch[0]->id);
        $this->assertSame(-1001122334455, $batch[1]->id);

        // Update progress to the last negative chat ID
        $this->db->broadcasts()->updateProgress($broadcast, lastChatId: -1001122334455, sentIncrement: 2);
        $this->assertSame(-1001122334455, $broadcast->lastOffset);

        // Next batch should return the positive chat IDs
        $nextBatch = $this->db->broadcasts()->fetchNextPendingBatch($broadcast, batchSize: 2);
        $this->assertNotEmpty($nextBatch);
        $this->assertGreaterThan(-1001122334455, $nextBatch[0]->id);
    }
}
