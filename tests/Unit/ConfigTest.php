<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\ConnectionConfig;
use Tueen\Database\Config\DatabaseConfig;

final class ConfigTest extends TestCase
{
    public function testSqliteConnectionConfig(): void
    {
        $config = ConnectionConfig::sqlite(':memory:');
        $params = $config->toDbalParams();

        $this->assertSame('pdo_sqlite', $params['driver']);
        $this->assertTrue($params['memory']);
    }

    public function testMysqlConnectionConfig(): void
    {
        $config = ConnectionConfig::mysql(
            dbname: 'bot_db',
            user: 'bot_user',
            password: 'secret_password',
            host: '10.0.0.1',
            port: 3307,
        );
        $params = $config->toDbalParams();

        $this->assertSame('pdo_mysql', $params['driver']);
        $this->assertSame('bot_db', $params['dbname']);
        $this->assertSame('bot_user', $params['user']);
        $this->assertSame('secret_password', $params['password']);
        $this->assertSame('10.0.0.1', $params['host']);
        $this->assertSame(3307, $params['port']);
    }

    public function testPostgresConnectionConfig(): void
    {
        $config = ConnectionConfig::postgres(
            dbname: 'pg_bot',
            user: 'pg_user',
            password: 'pg_password',
        );
        $params = $config->toDbalParams();

        $this->assertSame('pdo_pgsql', $params['driver']);
        $this->assertSame('pg_bot', $params['dbname']);
        $this->assertSame(5432, $params['port']);
    }

    public function testDatabaseConfigDefaultsAndImmutability(): void
    {
        $dbConfig = DatabaseConfig::createSqlite(':memory:', autoMigrate: true);

        $this->assertTrue($dbConfig->autoMigrate);
        $this->assertSame('pdo_sqlite', $dbConfig->connection->driver);
        $this->assertSame('XTR', $dbConfig->defaultCurrency);

        $extended = $dbConfig->withAdditionalEntityPath('/tmp/custom_entities');
        $this->assertContains('/tmp/custom_entities', $extended->entityPaths);
        $this->assertNotContains('/tmp/custom_entities', $dbConfig->entityPaths);
    }

    public function testDatabaseConfigDefaultCurrencyCustomization(): void
    {
        $dbConfig = DatabaseConfig::createSqlite(':memory:');
        $this->assertSame('XTR', $dbConfig->defaultCurrency);

        $custom = $dbConfig->withDefaultCurrency('TON');
        $this->assertSame('TON', $custom->defaultCurrency);
        $this->assertSame('XTR', $dbConfig->defaultCurrency);

        $fromArray = DatabaseConfig::fromArray([
            'driver' => 'sqlite',
            'path' => ':memory:',
            'default_currency' => 'USDT',
        ]);
        $this->assertSame('USDT', $fromArray->defaultCurrency);
    }
}
