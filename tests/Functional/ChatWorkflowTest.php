<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\ChatMeta;
use Tueen\Database\Enum\ChatType;

final class ChatWorkflowTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testFindOrCreateChat(): void
    {
        $chat = $this->db->chats()->findOrCreate(
            chatId: 987654321,
            username: '@super_bot_user',
            name: 'Ali Reza',
            type: ChatType::Private,
            languageCode: 'fa',
        );

        $this->assertInstanceOf(Chat::class, $chat);
        $this->assertSame(987654321, $chat->id);
        $this->assertSame('super_bot_user', $chat->username);
        $this->assertSame('Ali Reza', $chat->name);
        $this->assertSame('fa', $chat->languageCode);

        // Subsequent findOrCreate updates and returns the same entity
        $updated = $this->db->chats()->findOrCreate(
            chatId: 987654321,
            username: '@new_username',
            name: 'Ali R.',
        );

        $this->assertSame($chat->id, $updated->id);
        $this->assertSame('new_username', $updated->username);
        $this->assertSame('Ali R.', $updated->name);
    }

    public function testChatMetaAssociation(): void
    {
        $chat = $this->db->chats()->findOrCreate(1001, 'test_user');

        $meta = new ChatMeta($chat, 'theme', 'dark', 'preferences');
        $this->db->getEntityManager()->persist($meta);
        $this->db->getEntityManager()->flush();

        $savedMeta = $this->db->getEntityManager()->getRepository(ChatMeta::class)->findOneBy([
            'chat' => $chat->id,
            'metaKey' => 'theme',
        ]);

        $this->assertNotNull($savedMeta);
        $this->assertSame('dark', $savedMeta->value);
        $this->assertSame('preferences', $savedMeta->group);
    }

    public function testBanAndUnban(): void
    {
        $chat = $this->db->chats()->findOrCreate(2002);
        $this->assertFalse($chat->banned);

        $this->db->chats()->ban(2002);
        $this->assertTrue($chat->banned);

        $this->db->chats()->unban(2002);
        $this->assertFalse($chat->banned);
    }

    public function testContactRelationWithChat(): void
    {
        $chat = $this->db->chats()->findOrCreate(3003, 'contact_user');
        $contact = new \Tueen\Database\Entity\Contact(
            user: $chat,
            phoneNumber: '+19876543210',
            firstName: 'Sarah',
            lastName: 'Connor',
        );

        $this->db->getEntityManager()->persist($contact);
        $this->db->getEntityManager()->flush();

        $savedContact = $this->db->getEntityManager()->find(\Tueen\Database\Entity\Contact::class, 3003);
        $this->assertNotNull($savedContact);
        $this->assertSame(3003, $savedContact->userId);
        $this->assertSame('Sarah Connor', $savedContact->fullName);
        $this->assertSame('19876543210', $savedContact->phoneNumber);
    }

    public function testFinancialDeleteRestriction(): void
    {
        $chat = $this->db->chats()->findOrCreate(4004, 'financial_user');
        $this->db->wallets()->credit($chat, '100.000000000000000000', 'TON');

        // Attempting to delete a chat that has an active wallet balance or ledger transactions must fail with ForeignKeyConstraintViolationException
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);

        $em = $this->db->getEntityManager();
        $em->remove($chat);
        $em->flush();
    }

    public function testUsernameCollisionReleasesPreviousOwner(): void
    {
        // 1. User A claims username @durov
        $chatA = $this->db->chats()->findOrCreate(
            chatId: 111111,
            username: '@Durov',
            name: 'Pavel Durov Old',
        );
        $this->assertSame('durov', $chatA->username);

        // 2. User B claims the same username @durov (e.g. transfer or reuse)
        $chatB = $this->db->chats()->findOrCreate(
            chatId: 222222,
            username: '@durov',
            name: 'Pavel Durov New',
        );

        $this->assertSame('durov', $chatB->username);

        // Refresh User A from database to verify username was nulled out
        $this->db->getEntityManager()->refresh($chatA);
        $this->assertNull($chatA->username);

        // 3. User B updates details keeping their own username
        $chatBUpdated = $this->db->chats()->findOrCreate(
            chatId: 222222,
            username: '@durov',
            name: 'Pavel Durov Verified',
        );
        $this->assertSame('durov', $chatBUpdated->username);
        $this->assertSame('Pavel Durov Verified', $chatBUpdated->name);
    }

    public function testChatMetaFluentMethods(): void
    {
        $chat = $this->db->chats()->findOrCreate(5555, 'meta_tester');

        // Test setMeta and getMeta via ChatRepository
        $this->db->chats()->setMeta($chat, 'dark_mode', 'true', 'ui');
        $this->assertSame('true', $this->db->chats()->getMeta($chat, 'dark_mode'));
        $this->assertTrue($this->db->chats()->hasMeta($chat, 'dark_mode'));

        // Test default fallback
        $this->assertSame('en', $this->db->chats()->getMeta($chat, 'non_existing', default: 'en'));

        // Test deleteMeta
        $this->assertTrue($this->db->chats()->deleteMeta($chat, 'dark_mode'));
        $this->assertFalse($this->db->chats()->hasMeta($chat, 'dark_mode'));
        $this->assertNull($this->db->chats()->getMeta($chat, 'dark_mode'));

        // Test entity-level meta methods
        $chat->setMeta('language', 'fa');
        $this->assertSame('fa', $chat->getMeta('language'));
        $this->assertTrue($chat->hasMeta('language'));
        $this->assertTrue($chat->deleteMeta('language'));
        $this->assertFalse($chat->hasMeta('language'));
    }
}
