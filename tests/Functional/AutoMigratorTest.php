<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

final class AutoMigratorTest extends TestCase
{
    public function testZeroConfigAutoMigrationCreatesAllTables(): void
    {
        $db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:', autoMigrate: true));

        // Schema should already be automatically migrated on boot / first interaction
        $this->assertTrue($db->getAutoMigrator()->isSynced());
        $this->assertCount(0, $db->getAutoMigrator()->getPendingSql());

        $sm = $db->getConnection()->createSchemaManager();
        $tables = $sm->listTableNames();

        // Check key tables exist
        $this->assertContains('chats', $tables);
        $this->assertContains('wallet_balances', $tables);
        $this->assertContains('orders', $tables);
        $this->assertContains('coupons', $tables);
        $this->assertContains('subscriptions', $tables);
        $this->assertContains('broadcast_messages', $tables);
        $this->assertContains('options', $tables);
        $this->assertContains('histories', $tables);
    }
}
