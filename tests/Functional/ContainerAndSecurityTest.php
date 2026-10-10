<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\DependencyInjection\ContainerFactory;
use Tueen\Database\Security\CredentialManager;
use Tueen\Database\Security\PasswordHasherService;

final class ContainerAndSecurityTest extends TestCase
{
    public function testSymfonyDiContainerIntegration(): void
    {
        $config = DatabaseConfig::createSqlite(':memory:');
        $container = ContainerFactory::createContainer($config);

        $this->assertTrue($container->has(DatabaseManager::class));
        $this->assertTrue($container->has(PasswordHasherService::class));

        $dbManager = $container->get(DatabaseManager::class);
        $this->assertInstanceOf(DatabaseManager::class, $dbManager);

        $hasher = $container->get(PasswordHasherService::class);
        $this->assertInstanceOf(PasswordHasherService::class, $hasher);
    }

    public function testPasswordHasher(): void
    {
        $hasher = new PasswordHasherService();
        $hash = $hasher->hash('tueen_secret_token');

        $this->assertNotSame('tueen_secret_token', $hash);
        $this->assertTrue($hasher->verify($hash, 'tueen_secret_token'));
        $this->assertFalse($hasher->verify($hash, 'wrong_token'));
    }

    public function testCredentialManagerHelpers(): void
    {
        $_ENV['TEST_BOOL_TRUE'] = 'true';
        $_ENV['TEST_BOOL_FALSE'] = 'false';
        $_ENV['TEST_INT'] = '42';

        $this->assertTrue(CredentialManager::getBool('TEST_BOOL_TRUE', false));
        $this->assertFalse(CredentialManager::getBool('TEST_BOOL_FALSE', true));
        $this->assertTrue(CredentialManager::getBool('TEST_NON_EXISTENT', true));
        $this->assertFalse(CredentialManager::getBool('TEST_NON_EXISTENT', false));

        $this->assertSame(42, CredentialManager::getInt('TEST_INT', 0));
        $this->assertSame(99, CredentialManager::getInt('TEST_NON_EXISTENT', 99));

        unset($_ENV['TEST_BOOL_TRUE'], $_ENV['TEST_BOOL_FALSE'], $_ENV['TEST_INT']);
    }
}
