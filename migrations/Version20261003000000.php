<?php

declare(strict_types=1);

namespace Tueen\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tma_sessions table for Telegram Mini App auth sessions';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('tma_sessions')) {
            return;
        }

        $table = $schema->createTable('tma_sessions');
        $table->addColumn('id', 'bigint', ['autoincrement' => true]);
        $table->addColumn('public_id', 'string', ['length' => 26]);
        $table->addColumn('chat_id', 'bigint');
        $table->addColumn('token', 'string', ['length' => 64]);
        $table->addColumn('query_id', 'string', ['length' => 128, 'notnull' => false]);
        $table->addColumn('start_param', 'string', ['length' => 128, 'notnull' => false]);
        $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
        $table->addColumn('user_agent', 'string', ['length' => 512, 'notnull' => false]);
        $table->addColumn('payload', 'json', ['notnull' => false]);
        $table->addColumn('is_revoked', 'boolean', ['default' => false]);
        $table->addColumn('auth_date', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('last_active_at', 'datetime_immutable');
        $table->addColumn('expires_at', 'datetime_immutable');

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['token'], 'uk_tma_sessions_token');
        $table->addUniqueIndex(['public_id'], 'uk_tma_sessions_public_id');
        $table->addIndex(['chat_id', 'expires_at'], 'idx_tma_sessions_chat_expires');
        $table->addIndex(['is_revoked'], 'idx_tma_sessions_is_revoked');
        $table->addIndex(['expires_at'], 'idx_tma_sessions_expires_at');
        $table->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_tma_sessions_chat_id');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('tma_sessions')) {
            $schema->dropTable('tma_sessions');
        }
    }
}
