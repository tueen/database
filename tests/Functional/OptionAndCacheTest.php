<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;

final class OptionAndCacheTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testOptionGetSetDelete(): void
    {
        $this->assertFalse($this->db->options()->has('site_name'));
        $this->assertSame('Default Name', $this->db->options()->get('site_name', 'Default Name'));

        $this->db->options()->set('site_name', 'Tueen Telegram Bot', 'general');
        $this->assertTrue($this->db->options()->has('site_name'));
        $this->assertSame('Tueen Telegram Bot', $this->db->options()->get('site_name'));

        $group = $this->db->options()->getByGroup('general');
        $this->assertArrayHasKey('site_name', $group);
        $this->assertSame('Tueen Telegram Bot', $group['site_name']);

        $deleted = $this->db->options()->delete('site_name');
        $this->assertTrue($deleted);
        $this->assertFalse($this->db->options()->has('site_name'));
    }

    public function testPsr16Cache(): void
    {
        $cache = $this->db->cache();

        $this->assertFalse($cache->has('session_key'));
        $cache->set('session_key', ['step' => 'awaiting_email', 'attempts' => 1], 3600);

        $this->assertTrue($cache->has('session_key'));
        $val = $cache->get('session_key');
        $this->assertIsArray($val);
        $this->assertSame('awaiting_email', $val['step']);

        $cache->delete('session_key');
        $this->assertFalse($cache->has('session_key'));
    }
}
