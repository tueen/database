<?php

declare(strict_types=1);

namespace Tueen\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialize Tueen Database core schema for Telegram bots';
    }

    public function up(Schema $schema): void
    {
        // 1. chats
        $chats = $schema->createTable('chats');
        $chats->addColumn('id', 'bigint');
        $chats->addColumn('public_id', 'string', ['length' => 26]);
        $chats->addColumn('type', 'string', ['length' => 20, 'default' => 'private']);
        $chats->addColumn('username', 'string', ['length' => 64, 'notnull' => false]);
        $chats->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
        $chats->addColumn('language_code', 'string', ['length' => 10, 'notnull' => false]);
        $chats->addColumn('accessible', 'boolean', ['default' => true]);
        $chats->addColumn('banned', 'boolean', ['default' => false]);
        $chats->addColumn('created_at', 'datetime_immutable');
        $chats->addColumn('active_at', 'datetime_immutable');
        $chats->addColumn('updated_at', 'datetime_immutable');
        $chats->setPrimaryKey(['id']);
        $chats->addUniqueIndex(['public_id'], 'uk_chats_public_id');
        $chats->addUniqueIndex(['username'], 'uk_chats_username');
        $chats->addIndex(['type', 'banned', 'accessible'], 'idx_chats_type_banned_accessible');
        $chats->addIndex(['type', 'active_at'], 'idx_chats_type_active_at');
        $chats->addIndex(['created_at'], 'idx_chats_created_at');

        // 2. contacts
        $contacts = $schema->createTable('contacts');
        $contacts->addColumn('user_id', 'bigint');
        $contacts->addColumn('phone_number', 'string', ['length' => 32]);
        $contacts->addColumn('first_name', 'string', ['length' => 100]);
        $contacts->addColumn('last_name', 'string', ['length' => 100, 'notnull' => false]);
        $contacts->addColumn('created_at', 'datetime_immutable');
        $contacts->addColumn('updated_at', 'datetime_immutable');
        $contacts->setPrimaryKey(['user_id']);
        $contacts->addUniqueIndex(['phone_number'], 'uk_contacts_phone_number');
        $contacts->addForeignKeyConstraint('chats', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_contacts_user_id');

        // 3. chat_meta
        $chatMeta = $schema->createTable('chat_meta');
        $chatMeta->addColumn('chat_id', 'bigint');
        $chatMeta->addColumn('meta_key', 'string', ['length' => 128]);
        $chatMeta->addColumn('value', 'json', ['notnull' => false]);
        $chatMeta->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $chatMeta->addColumn('created_at', 'datetime_immutable');
        $chatMeta->addColumn('updated_at', 'datetime_immutable');
        $chatMeta->setPrimaryKey(['chat_id', 'meta_key']);
        $chatMeta->addIndex(['group_name', 'chat_id'], 'idx_chat_meta_group_chat_id');
        $chatMeta->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_meta_chat_id');

        // 4. options
        $options = $schema->createTable('options');
        $options->addColumn('name', 'string', ['length' => 128]);
        $options->addColumn('value', 'json', ['notnull' => false]);
        $options->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $options->addColumn('created_at', 'datetime_immutable');
        $options->addColumn('updated_at', 'datetime_immutable');
        $options->setPrimaryKey(['name']);
        $options->addIndex(['group_name'], 'idx_options_group');

        // 5. wallet_balances
        $wallets = $schema->createTable('wallet_balances');
        $wallets->addColumn('chat_id', 'bigint');
        $wallets->addColumn('currency', 'string', ['length' => 10]);
        $wallets->addColumn('balance', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $wallets->addColumn('total_consumed', 'decimal', ['precision' => 40, 'scale' => 18, 'default' => '0.000000000000000000']);
        $wallets->addColumn('created_at', 'datetime_immutable');
        $wallets->addColumn('updated_at', 'datetime_immutable');
        $wallets->setPrimaryKey(['chat_id', 'currency']);
        $wallets->addIndex(['currency'], 'idx_wallet_balances_currency');
        $wallets->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_wallet_balances_chat_id');

        // 6. wallet_transactions
        $walletTrans = $schema->createTable('wallet_transactions');
        $walletTrans->addColumn('id', 'bigint', ['autoincrement' => true]);
        $walletTrans->addColumn('public_id', 'string', ['length' => 26]);
        $walletTrans->addColumn('chat_id', 'bigint');
        $walletTrans->addColumn('currency', 'string', ['length' => 10]);
        $walletTrans->addColumn('amount', 'decimal', ['precision' => 36, 'scale' => 18]);
        $walletTrans->addColumn('balance_before', 'decimal', ['precision' => 36, 'scale' => 18]);
        $walletTrans->addColumn('balance_after', 'decimal', ['precision' => 36, 'scale' => 18]);
        $walletTrans->addColumn('total_consumed_before', 'decimal', ['precision' => 40, 'scale' => 18]);
        $walletTrans->addColumn('total_consumed_after', 'decimal', ['precision' => 40, 'scale' => 18]);
        $walletTrans->addColumn('source', 'string', ['length' => 32, 'notnull' => false]);
        $walletTrans->addColumn('source_id', 'bigint', ['notnull' => false]);
        $walletTrans->addColumn('created_by', 'bigint', ['notnull' => false]);
        $walletTrans->addColumn('created_at', 'datetime_immutable');
        $walletTrans->setPrimaryKey(['id']);
        $walletTrans->addUniqueIndex(['public_id'], 'uk_wallet_trans_public_id');
        $walletTrans->addIndex(['chat_id', 'currency', 'created_at'], 'idx_wallet_trans_chat_curr_created');
        $walletTrans->addIndex(['created_at'], 'idx_wallet_trans_created_at');
        $walletTrans->addIndex(['created_by'], 'idx_wallet_trans_created_by');
        $walletTrans->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_wallet_transactions_chat_id');
        $walletTrans->addForeignKeyConstraint('chats', ['created_by'], ['id'], ['onDelete' => 'SET NULL'], 'fk_wallet_transactions_created_by');

        // 7. treasury
        $treasury = $schema->createTable('treasury');
        $treasury->addColumn('currency', 'string', ['length' => 10]);
        $treasury->addColumn('balance', 'decimal', ['precision' => 40, 'scale' => 18, 'default' => '0.000000000000000000']);
        $treasury->addColumn('total_commission', 'decimal', ['precision' => 40, 'scale' => 18, 'default' => '0.000000000000000000']);
        $treasury->addColumn('total_consumed', 'decimal', ['precision' => 40, 'scale' => 18, 'default' => '0.000000000000000000']);
        $treasury->addColumn('created_at', 'datetime_immutable');
        $treasury->addColumn('updated_at', 'datetime_immutable');
        $treasury->setPrimaryKey(['currency']);

        // 8. treasury_transactions
        $treasuryTrans = $schema->createTable('treasury_transactions');
        $treasuryTrans->addColumn('id', 'bigint', ['autoincrement' => true]);
        $treasuryTrans->addColumn('public_id', 'string', ['length' => 26]);
        $treasuryTrans->addColumn('currency', 'string', ['length' => 10]);
        $treasuryTrans->addColumn('amount', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('balance_before', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('balance_after', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('total_commission_before', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('total_commission_after', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('total_consumed_before', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('total_consumed_after', 'decimal', ['precision' => 40, 'scale' => 18]);
        $treasuryTrans->addColumn('source', 'string', ['length' => 32, 'notnull' => false]);
        $treasuryTrans->addColumn('source_id', 'bigint', ['notnull' => false]);
        $treasuryTrans->addColumn('created_by', 'bigint', ['notnull' => false]);
        $treasuryTrans->addColumn('created_at', 'datetime_immutable');
        $treasuryTrans->setPrimaryKey(['id']);
        $treasuryTrans->addUniqueIndex(['public_id'], 'uk_treasury_trans_public_id');
        $treasuryTrans->addIndex(['currency', 'created_at'], 'idx_treasury_trans_curr_created');
        $treasuryTrans->addIndex(['source'], 'idx_treasury_trans_source');
        $treasuryTrans->addIndex(['created_by'], 'idx_treasury_trans_created_by');
        $treasuryTrans->addIndex(['created_at'], 'idx_treasury_trans_created_at');
        $treasuryTrans->addForeignKeyConstraint('chats', ['created_by'], ['id'], ['onDelete' => 'SET NULL'], 'fk_treasury_transactions_created_by');

        // 9. coupons
        $coupons = $schema->createTable('coupons');
        $coupons->addColumn('id', 'bigint', ['autoincrement' => true]);
        $coupons->addColumn('code', 'string', ['length' => 64]);
        $coupons->addColumn('title', 'string', ['length' => 255]);
        $coupons->addColumn('description', 'text', ['notnull' => false]);
        $coupons->addColumn('type', 'string', ['length' => 32, 'default' => 'percent']);
        $coupons->addColumn('status', 'string', ['length' => 20, 'default' => 'active']);
        $coupons->addColumn('value', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $coupons->addColumn('currency', 'string', ['length' => 10, 'notnull' => false]);
        $coupons->addColumn('max_uses', 'integer', ['notnull' => false]);
        $coupons->addColumn('max_uses_per_user', 'integer', ['notnull' => false]);
        $coupons->addColumn('min_order_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'notnull' => false]);
        $coupons->addColumn('allowed_order_types', 'json', ['notnull' => false]);
        $coupons->addColumn('allowed_users', 'json', ['notnull' => false]);
        $coupons->addColumn('extra', 'json', ['notnull' => false]);
        $coupons->addColumn('start_at', 'datetime_immutable', ['notnull' => false]);
        $coupons->addColumn('end_at', 'datetime_immutable', ['notnull' => false]);
        $coupons->addColumn('created_at', 'datetime_immutable');
        $coupons->addColumn('updated_at', 'datetime_immutable');
        $coupons->setPrimaryKey(['id']);
        $coupons->addUniqueIndex(['code'], 'uk_coupons_code');
        $coupons->addIndex(['status', 'start_at', 'end_at'], 'idx_coupons_status_start_end');
        $coupons->addIndex(['type', 'status'], 'idx_coupons_type_status');
        $coupons->addIndex(['created_at'], 'idx_coupons_created_at');

        // 10. coupon_meta
        $couponMeta = $schema->createTable('coupon_meta');
        $couponMeta->addColumn('coupon_id', 'bigint');
        $couponMeta->addColumn('meta_key', 'string', ['length' => 128]);
        $couponMeta->addColumn('value', 'json', ['notnull' => false]);
        $couponMeta->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $couponMeta->addColumn('created_at', 'datetime_immutable');
        $couponMeta->addColumn('updated_at', 'datetime_immutable');
        $couponMeta->setPrimaryKey(['coupon_id', 'meta_key']);
        $couponMeta->addIndex(['group_name', 'coupon_id'], 'idx_coupon_meta_group_coupon_id');
        $couponMeta->addForeignKeyConstraint('coupons', ['coupon_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_coupon_meta_coupon_id');

        // 11. orders
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'bigint', ['autoincrement' => true]);
        $orders->addColumn('public_id', 'string', ['length' => 26]);
        $orders->addColumn('chat_id', 'bigint', ['notnull' => false]);
        $orders->addColumn('type', 'string', ['length' => 50]);
        $orders->addColumn('status', 'string', ['length' => 20, 'default' => 'pending']);
        $orders->addColumn('priority', 'smallint', ['default' => 0]);
        $orders->addColumn('currency', 'string', ['length' => 10, 'default' => 'XTR']);
        $orders->addColumn('subtotal_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('discount_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('tax_mode', 'string', ['length' => 20, 'default' => 'exclusive']);
        $orders->addColumn('tax_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('shipping_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('payment_method', 'string', ['length' => 32, 'notnull' => false]);
        $orders->addColumn('payment_currency', 'string', ['length' => 10, 'default' => 'XTR']);
        $orders->addColumn('paid_currency', 'string', ['length' => 10, 'default' => 'XTR']);
        $orders->addColumn('paid_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('gateway_fee_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('transaction_id', 'string', ['length' => 255, 'notnull' => false]);
        $orders->addColumn('reference_id', 'string', ['length' => 255, 'notnull' => false]);
        $orders->addColumn('refunded_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $orders->addColumn('coupon_id', 'bigint', ['notnull' => false]);
        $orders->addColumn('coupon_code', 'string', ['length' => 64, 'notnull' => false]);
        $orders->addColumn('coupon_snapshot', 'json', ['notnull' => false]);
        $orders->addColumn('payload', 'json', ['notnull' => false]);
        $orders->addColumn('description', 'text', ['notnull' => false]);
        $orders->addColumn('admin_note', 'text', ['notnull' => false]);
        $orders->addColumn('paid_at', 'datetime_immutable', ['notnull' => false]);
        $orders->addColumn('refunded_at', 'datetime_immutable', ['notnull' => false]);
        $orders->addColumn('rejected_at', 'datetime_immutable', ['notnull' => false]);
        $orders->addColumn('created_at', 'datetime_immutable');
        $orders->addColumn('updated_at', 'datetime_immutable');
        $orders->setPrimaryKey(['id']);
        $orders->addUniqueIndex(['public_id'], 'uk_orders_public_id');
        $orders->addIndex(['chat_id', 'created_at'], 'idx_orders_chat_id_created_at');
        $orders->addIndex(['type', 'status', 'created_at'], 'idx_orders_type_status_created_at');
        $orders->addIndex(['status', 'created_at'], 'idx_orders_status_created_at');
        $orders->addIndex(['currency'], 'idx_orders_currency');
        $orders->addIndex(['coupon_id'], 'idx_orders_coupon_id');
        $orders->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_orders_chat_id');
        $orders->addForeignKeyConstraint('coupons', ['coupon_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_orders_coupon_id');

        // 12. coupon_usages
        $couponUsages = $schema->createTable('coupon_usages');
        $couponUsages->addColumn('id', 'bigint', ['autoincrement' => true]);
        $couponUsages->addColumn('coupon_id', 'bigint');
        $couponUsages->addColumn('chat_id', 'bigint');
        $couponUsages->addColumn('order_id', 'bigint');
        $couponUsages->addColumn('reverted', 'boolean', ['default' => false]);
        $couponUsages->addColumn('discount_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $couponUsages->addColumn('extra', 'json', ['notnull' => false]);
        $couponUsages->addColumn('used_at', 'datetime_immutable');
        $couponUsages->addColumn('reverted_at', 'datetime_immutable', ['notnull' => false]);
        $couponUsages->setPrimaryKey(['id']);
        $couponUsages->addUniqueIndex(['coupon_id', 'chat_id', 'order_id'], 'uk_coupon_usages_unique_use');
        $couponUsages->addIndex(['coupon_id'], 'idx_coupon_usages_coupon');
        $couponUsages->addIndex(['chat_id'], 'idx_coupon_usages_chat');
        $couponUsages->addIndex(['order_id'], 'idx_coupon_usages_order');
        $couponUsages->addIndex(['reverted'], 'idx_coupon_usages_reverted');
        $couponUsages->addIndex(['used_at'], 'idx_coupon_usages_used');
        $couponUsages->addForeignKeyConstraint('coupons', ['coupon_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_coupon_usages_coupon_id');
        $couponUsages->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_coupon_usages_chat_id');
        $couponUsages->addForeignKeyConstraint('orders', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_coupon_usages_order_id');

        // 13. order_meta
        $orderMeta = $schema->createTable('order_meta');
        $orderMeta->addColumn('order_id', 'bigint');
        $orderMeta->addColumn('meta_key', 'string', ['length' => 128]);
        $orderMeta->addColumn('value', 'json', ['notnull' => false]);
        $orderMeta->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $orderMeta->addColumn('created_at', 'datetime_immutable');
        $orderMeta->addColumn('updated_at', 'datetime_immutable');
        $orderMeta->setPrimaryKey(['order_id', 'meta_key']);
        $orderMeta->addIndex(['group_name', 'order_id'], 'idx_order_meta_group_order_id');
        $orderMeta->addForeignKeyConstraint('orders', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_order_meta_order_id');

        // 14. chat_likes & chat_ratings
        $chatLikes = $schema->createTable('chat_likes');
        $chatLikes->addColumn('chat_id', 'bigint');
        $chatLikes->addColumn('target_id', 'bigint');
        $chatLikes->addColumn('created_at', 'datetime_immutable');
        $chatLikes->setPrimaryKey(['chat_id', 'target_id']);
        $chatLikes->addIndex(['target_id'], 'idx_chat_likes_target_id');
        $chatLikes->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_likes_chat_id');
        $chatLikes->addForeignKeyConstraint('chats', ['target_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_likes_target_id');

        $chatRatings = $schema->createTable('chat_ratings');
        $chatRatings->addColumn('chat_id', 'bigint');
        $chatRatings->addColumn('target_id', 'bigint');
        $chatRatings->addColumn('rating', 'smallint');
        $chatRatings->addColumn('created_at', 'datetime_immutable');
        $chatRatings->addColumn('updated_at', 'datetime_immutable');
        $chatRatings->setPrimaryKey(['chat_id', 'target_id']);
        $chatRatings->addIndex(['target_id'], 'idx_chat_ratings_target_id');
        $chatRatings->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_ratings_chat_id');
        $chatRatings->addForeignKeyConstraint('chats', ['target_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_ratings_target_id');

        // 15. chat_xp & chat_xp_transactions
        $chatXp = $schema->createTable('chat_xp');
        $chatXp->addColumn('chat_id', 'bigint');
        $chatXp->addColumn('xp', 'bigint', ['default' => 0]);
        $chatXp->addColumn('created_at', 'datetime_immutable');
        $chatXp->addColumn('updated_at', 'datetime_immutable');
        $chatXp->setPrimaryKey(['chat_id']);
        $chatXp->addIndex(['xp', 'chat_id'], 'idx_chat_xp_ranking');
        $chatXp->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_chat_xp_chat_id');

        $xpTrans = $schema->createTable('chat_xp_transactions');
        $xpTrans->addColumn('id', 'bigint', ['autoincrement' => true]);
        $xpTrans->addColumn('public_id', 'string', ['length' => 26]);
        $xpTrans->addColumn('chat_id', 'bigint');
        $xpTrans->addColumn('value', 'bigint');
        $xpTrans->addColumn('xp_before', 'bigint');
        $xpTrans->addColumn('xp_after', 'bigint');
        $xpTrans->addColumn('source', 'string', ['length' => 32, 'notnull' => false]);
        $xpTrans->addColumn('source_id', 'bigint', ['notnull' => false]);
        $xpTrans->addColumn('created_by', 'bigint', ['notnull' => false]);
        $xpTrans->addColumn('created_at', 'datetime_immutable');
        $xpTrans->addColumn('updated_at', 'datetime_immutable');
        $xpTrans->setPrimaryKey(['id']);
        $xpTrans->addUniqueIndex(['public_id'], 'uk_chat_xp_trans_public_id');
        $xpTrans->addIndex(['chat_id'], 'idx_chat_xp_trans_chat_id');
        $xpTrans->addIndex(['created_by'], 'idx_chat_xp_trans_created_by');
        $xpTrans->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_chat_xp_trans_chat_id');
        $xpTrans->addForeignKeyConstraint('chats', ['created_by'], ['id'], ['onDelete' => 'SET NULL'], 'fk_chat_xp_trans_created_by');

        // 16. subscriptions & subscription_transactions
        $subs = $schema->createTable('subscriptions');
        $subs->addColumn('chat_id', 'bigint');
        $subs->addColumn('type', 'string', ['length' => 32, 'default' => 'vip']);
        $subs->addColumn('expires_at', 'datetime_immutable');
        $subs->addColumn('total_added_seconds', 'bigint', ['default' => 0]);
        $subs->addColumn('created_at', 'datetime_immutable');
        $subs->addColumn('updated_at', 'datetime_immutable');
        $subs->setPrimaryKey(['chat_id', 'type']);
        $subs->addIndex(['chat_id', 'expires_at'], 'idx_subscriptions_chat_expires');
        $subs->addIndex(['expires_at'], 'idx_subscriptions_expires');
        $subs->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_subscriptions_chat_id');

        $subTrans = $schema->createTable('subscription_transactions');
        $subTrans->addColumn('id', 'bigint', ['autoincrement' => true]);
        $subTrans->addColumn('public_id', 'string', ['length' => 26]);
        $subTrans->addColumn('chat_id', 'bigint');
        $subTrans->addColumn('type', 'string', ['length' => 32]);
        $subTrans->addColumn('expires_at_before', 'datetime_immutable', ['notnull' => false]);
        $subTrans->addColumn('expires_at_after', 'datetime_immutable', ['notnull' => false]);
        $subTrans->addColumn('total_added_seconds_before', 'bigint', ['default' => 0]);
        $subTrans->addColumn('total_added_seconds_after', 'bigint', ['default' => 0]);
        $subTrans->addColumn('source', 'string', ['length' => 32, 'notnull' => false]);
        $subTrans->addColumn('source_id', 'bigint', ['notnull' => false]);
        $subTrans->addColumn('created_by', 'bigint', ['notnull' => false]);
        $subTrans->addColumn('created_at', 'datetime_immutable');
        $subTrans->setPrimaryKey(['id']);
        $subTrans->addUniqueIndex(['public_id'], 'uk_sub_trans_public_id');
        $subTrans->addIndex(['chat_id', 'type', 'source'], 'idx_sub_trans_chat_type_source');
        $subTrans->addIndex(['created_by'], 'idx_sub_trans_created_by');
        $subTrans->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_sub_trans_chat_id');
        $subTrans->addForeignKeyConstraint('chats', ['created_by'], ['id'], ['onDelete' => 'SET NULL'], 'fk_sub_trans_created_by');

        // 17. referrals
        $referrals = $schema->createTable('referrals');
        $referrals->addColumn('referred_id', 'bigint');
        $referrals->addColumn('referrer_id', 'bigint');
        $referrals->addColumn('status', 'string', ['length' => 20, 'default' => 'pending']);
        $referrals->addColumn('created_at', 'datetime_immutable');
        $referrals->addColumn('qualified_at', 'datetime_immutable', ['notnull' => false]);
        $referrals->addColumn('updated_at', 'datetime_immutable');
        $referrals->setPrimaryKey(['referred_id']);
        $referrals->addIndex(['referrer_id', 'status'], 'idx_referrals_referrer_status');
        $referrals->addIndex(['status'], 'idx_referrals_status');
        $referrals->addForeignKeyConstraint('chats', ['referred_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_referrals_referred_id');
        $referrals->addForeignKeyConstraint('chats', ['referrer_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_referrals_referrer_id');

        // 18. withdrawals & withdrawal_meta
        $withdrawals = $schema->createTable('withdrawals');
        $withdrawals->addColumn('id', 'bigint', ['autoincrement' => true]);
        $withdrawals->addColumn('status', 'string', ['length' => 20, 'default' => 'pending']);
        $withdrawals->addColumn('chat_id', 'bigint', ['notnull' => false]);
        $withdrawals->addColumn('processed_by', 'bigint', ['notnull' => false]);
        $withdrawals->addColumn('currency', 'string', ['length' => 10, 'notnull' => false]);
        $withdrawals->addColumn('commission_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => '0.00']);
        $withdrawals->addColumn('amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $withdrawals->addColumn('fee_amount', 'decimal', ['precision' => 36, 'scale' => 18, 'default' => '0.000000000000000000']);
        $withdrawals->addColumn('payout_method', 'string', ['length' => 32, 'notnull' => false]);
        $withdrawals->addColumn('destination', 'string', ['length' => 255, 'notnull' => false]);
        $withdrawals->addColumn('transaction_id', 'string', ['length' => 255, 'notnull' => false]);
        $withdrawals->addColumn('reference_id', 'string', ['length' => 255, 'notnull' => false]);
        $withdrawals->addColumn('extra', 'json', ['notnull' => false]);
        $withdrawals->addColumn('description', 'text', ['notnull' => false]);
        $withdrawals->addColumn('admin_note', 'text', ['notnull' => false]);
        $withdrawals->addColumn('created_at', 'datetime_immutable');
        $withdrawals->addColumn('processed_at', 'datetime_immutable', ['notnull' => false]);
        $withdrawals->addColumn('updated_at', 'datetime_immutable');
        $withdrawals->setPrimaryKey(['id']);
        $withdrawals->addIndex(['status', 'created_at'], 'idx_withdrawals_status_created');
        $withdrawals->addIndex(['chat_id', 'created_at'], 'idx_withdrawals_chat_created_at');
        $withdrawals->addIndex(['currency'], 'idx_withdrawals_currency');
        $withdrawals->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_withdrawals_chat_id');
        $withdrawals->addForeignKeyConstraint('chats', ['processed_by'], ['id'], ['onDelete' => 'SET NULL'], 'fk_withdrawals_processed_by');

        $withMeta = $schema->createTable('withdrawal_meta');
        $withMeta->addColumn('withdrawal_id', 'bigint');
        $withMeta->addColumn('meta_key', 'string', ['length' => 128]);
        $withMeta->addColumn('value', 'json', ['notnull' => false]);
        $withMeta->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $withMeta->addColumn('created_at', 'datetime_immutable');
        $withMeta->addColumn('updated_at', 'datetime_immutable');
        $withMeta->setPrimaryKey(['withdrawal_id', 'meta_key']);
        $withMeta->addIndex(['group_name', 'withdrawal_id'], 'idx_withdrawal_meta_group_withdrawal_id');
        $withMeta->addForeignKeyConstraint('withdrawals', ['withdrawal_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_withdrawal_meta_withdrawal_id');

        // 19. broadcast_messages
        $broadcasts = $schema->createTable('broadcast_messages');
        $broadcasts->addColumn('id', 'bigint', ['autoincrement' => true]);
        $broadcasts->addColumn('status', 'string', ['length' => 20, 'default' => 'draft']);
        $broadcasts->addColumn('source_chat_id', 'bigint');
        $broadcasts->addColumn('source_message_id', 'bigint');
        $broadcasts->addColumn('priority', 'smallint', ['default' => 0]);
        $broadcasts->addColumn('allow_private_chats', 'boolean', ['default' => true]);
        $broadcasts->addColumn('allow_group_chats', 'boolean', ['default' => false]);
        $broadcasts->addColumn('allow_supergroup_chats', 'boolean', ['default' => false]);
        $broadcasts->addColumn('allow_channel_chats', 'boolean', ['default' => false]);
        $broadcasts->addColumn('excluded_chat_ids', 'json', ['notnull' => false]);
        $broadcasts->addColumn('last_offset', 'bigint', ['default' => 0]);
        $broadcasts->addColumn('initial_chat_count', 'bigint', ['default' => 0]);
        $broadcasts->addColumn('sent_count', 'bigint', ['default' => 0]);
        $broadcasts->addColumn('failed_count', 'bigint', ['default' => 0]);
        $broadcasts->addColumn('ignored_count', 'bigint', ['default' => 0]);
        $broadcasts->addColumn('extra', 'json', ['notnull' => false]);
        $broadcasts->addColumn('created_at', 'datetime_immutable');
        $broadcasts->addColumn('last_sent_at', 'datetime_immutable', ['notnull' => false]);
        $broadcasts->addColumn('updated_at', 'datetime_immutable');
        $broadcasts->setPrimaryKey(['id']);
        $broadcasts->addIndex(['source_chat_id'], 'idx_broadcast_messages_source_chat');
        $broadcasts->addIndex(['status'], 'idx_broadcast_messages_status');
        $broadcasts->addIndex(['priority'], 'idx_broadcast_messages_priority');
        $broadcasts->addForeignKeyConstraint('chats', ['source_chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_broadcast_messages_source_chat_id');

        // 20. entities & entity relations
        $entities = $schema->createTable('entities');
        $entities->addColumn('id', 'bigint', ['autoincrement' => true]);
        $entities->addColumn('public_id', 'string', ['length' => 26]);
        $entities->addColumn('type', 'string', ['length' => 50, 'default' => 'entity']);
        $entities->addColumn('slug', 'string', ['length' => 120]);
        $entities->addColumn('status', 'string', ['length' => 20, 'default' => 'draft']);
        $entities->addColumn('chat_id', 'bigint');
        $entities->addColumn('parent_id', 'bigint', ['notnull' => false]);
        $entities->addColumn('priority', 'smallint', ['default' => 0]);
        $entities->addColumn('title', 'string', ['length' => 255, 'notnull' => false]);
        $entities->addColumn('content', 'text', ['notnull' => false]);
        $entities->addColumn('extra', 'json', ['notnull' => false]);
        $entities->addColumn('created_at', 'datetime_immutable');
        $entities->addColumn('updated_at', 'datetime_immutable');
        $entities->setPrimaryKey(['id']);
        $entities->addUniqueIndex(['public_id'], 'uk_entities_public_id');
        $entities->addUniqueIndex(['type', 'slug'], 'uk_entities_type_slug');
        $entities->addIndex(['chat_id'], 'idx_entities_chat');
        $entities->addIndex(['parent_id'], 'idx_entities_parent');
        $entities->addIndex(['type', 'status', 'created_at'], 'idx_entities_type_status_created');
        $entities->addIndex(['chat_id', 'type', 'status'], 'idx_entities_chat_type_status');
        $entities->addIndex(['priority', 'created_at'], 'idx_entities_priority_created');
        $entities->addIndex(['created_at'], 'idx_entities_created');
        $entities->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entities_chat_id');
        $entities->addForeignKeyConstraint('entities', ['parent_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entities_parent_id');

        $entityMeta = $schema->createTable('entity_meta');
        $entityMeta->addColumn('entity_id', 'bigint');
        $entityMeta->addColumn('meta_key', 'string', ['length' => 128]);
        $entityMeta->addColumn('value', 'json', ['notnull' => false]);
        $entityMeta->addColumn('group_name', 'string', ['length' => 64, 'notnull' => false]);
        $entityMeta->addColumn('created_at', 'datetime_immutable');
        $entityMeta->addColumn('updated_at', 'datetime_immutable');
        $entityMeta->setPrimaryKey(['entity_id', 'meta_key']);
        $entityMeta->addIndex(['group_name', 'entity_id'], 'idx_entity_meta_group_entity_id');
        $entityMeta->addForeignKeyConstraint('entities', ['entity_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entity_meta_entity_id');

        $entityLikes = $schema->createTable('entity_likes');
        $entityLikes->addColumn('chat_id', 'bigint');
        $entityLikes->addColumn('target_id', 'bigint');
        $entityLikes->addColumn('created_at', 'datetime_immutable');
        $entityLikes->setPrimaryKey(['chat_id', 'target_id']);
        $entityLikes->addIndex(['target_id'], 'idx_entity_likes_target_id');
        $entityLikes->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entity_likes_chat_id');
        $entityLikes->addForeignKeyConstraint('entities', ['target_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entity_likes_target_id');

        $entityRatings = $schema->createTable('entity_ratings');
        $entityRatings->addColumn('chat_id', 'bigint');
        $entityRatings->addColumn('target_id', 'bigint');
        $entityRatings->addColumn('rating', 'smallint');
        $entityRatings->addColumn('created_at', 'datetime_immutable');
        $entityRatings->addColumn('updated_at', 'datetime_immutable');
        $entityRatings->setPrimaryKey(['chat_id', 'target_id']);
        $entityRatings->addIndex(['target_id'], 'idx_entity_ratings_target_id');
        $entityRatings->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entity_ratings_chat_id');
        $entityRatings->addForeignKeyConstraint('entities', ['target_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_entity_ratings_target_id');

        // 21. message_relays
        $relays = $schema->createTable('message_relays');
        $relays->addColumn('chat_id1', 'bigint');
        $relays->addColumn('message_id1', 'bigint');
        $relays->addColumn('chat_id2', 'bigint');
        $relays->addColumn('message_id2', 'bigint');
        $relays->addColumn('message', 'json', ['notnull' => false]);
        $relays->addColumn('created_at', 'datetime_immutable');
        $relays->addColumn('updated_at', 'datetime_immutable');
        $relays->setPrimaryKey(['chat_id1', 'message_id1', 'chat_id2']);
        $relays->addUniqueIndex(['chat_id2', 'message_id2'], 'uk_message_relays_tgt');
        $relays->addIndex(['chat_id1', 'chat_id2'], 'idx_message_relays_chat_ids');
        $relays->addForeignKeyConstraint('chats', ['chat_id1'], ['id'], ['onDelete' => 'CASCADE'], 'fk_message_relays_chat_id1');
        $relays->addForeignKeyConstraint('chats', ['chat_id2'], ['id'], ['onDelete' => 'CASCADE'], 'fk_message_relays_chat_id2');

        // 22. data
        $data = $schema->createTable('data');
        $data->addColumn('id', 'bigint', ['autoincrement' => true]);
        $data->addColumn('group_name', 'string', ['length' => 64]);
        $data->addColumn('name', 'string', ['length' => 128]);
        $data->addColumn('chat_id', 'bigint', ['notnull' => false]);
        $data->addColumn('value', 'blob', ['notnull' => false]);
        $data->addColumn('created_at', 'datetime_immutable');
        $data->addColumn('updated_at', 'datetime_immutable');
        $data->setPrimaryKey(['id']);
        $data->addUniqueIndex(['group_name', 'name'], 'uk_data');
        $data->addIndex(['chat_id', 'group_name'], 'idx_data_chat_group');
        $data->addForeignKeyConstraint('chats', ['chat_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_data_chat_id');

        // 23. cache
        $cache = $schema->createTable('cache');
        $cache->addColumn('name', 'string', ['length' => 128]);
        $cache->addColumn('value', 'json', ['notnull' => false]);
        $cache->addColumn('creation_time', 'bigint');
        $cache->addColumn('expiration_time', 'bigint', ['notnull' => false]);
        $cache->addColumn('update_time', 'bigint');
        $cache->setPrimaryKey(['name']);

        // 24. histories
        $histories = $schema->createTable('histories');
        $histories->addColumn('id', 'bigint', ['autoincrement' => true]);
        $histories->addColumn('target_type', 'string', ['length' => 64]);
        $histories->addColumn('target_id', 'bigint');
        $histories->addColumn('chat_id', 'bigint', ['notnull' => false]);
        $histories->addColumn('action', 'string', ['length' => 64]);
        $histories->addColumn('field_name', 'string', ['length' => 100, 'notnull' => false]);
        $histories->addColumn('old_value', 'text', ['notnull' => false]);
        $histories->addColumn('new_value', 'text', ['notnull' => false]);
        $histories->addColumn('old_status', 'string', ['length' => 20, 'notnull' => false]);
        $histories->addColumn('new_status', 'string', ['length' => 20, 'notnull' => false]);
        $histories->addColumn('request_id', 'string', ['length' => 64, 'notnull' => false]);
        $histories->addColumn('extra', 'json', ['notnull' => false]);
        $histories->addColumn('created_at', 'datetime_immutable');
        $histories->setPrimaryKey(['id']);
        $histories->addIndex(['target_type', 'target_id', 'created_at'], 'idx_histories_target');
        $histories->addIndex(['chat_id', 'created_at'], 'idx_histories_chat_created');
        $histories->addIndex(['action', 'created_at'], 'idx_histories_action_created');
        $histories->addIndex(['request_id'], 'idx_histories_request');

        // 25. schema_versions
        $schemaVersions = $schema->createTable('schema_versions');
        $schemaVersions->addColumn('id', 'integer', ['autoincrement' => true]);
        $schemaVersions->addColumn('name', 'string', ['length' => 128]);
        $schemaVersions->addColumn('version', 'integer');
        $schemaVersions->addColumn('applied_at', 'datetime_immutable');
        $schemaVersions->addColumn('description', 'string', ['length' => 255, 'notnull' => false]);
        $schemaVersions->setPrimaryKey(['id']);
        $schemaVersions->addUniqueIndex(['name'], 'uk_schema_versions_name');
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'schema_versions',
            'histories',
            'cache',
            'data',
            'message_relays',
            'entity_ratings',
            'entity_likes',
            'entity_meta',
            'entities',
            'broadcast_messages',
            'withdrawal_meta',
            'withdrawals',
            'referrals',
            'subscription_transactions',
            'subscriptions',
            'chat_xp_transactions',
            'chat_xp',
            'chat_ratings',
            'chat_likes',
            'order_meta',
            'coupon_usages',
            'orders',
            'coupon_meta',
            'coupons',
            'treasury_transactions',
            'treasury',
            'wallet_transactions',
            'wallet_balances',
            'options',
            'chat_meta',
            'contacts',
            'chats',
        ];

        foreach ($tables as $table) {
            if ($schema->hasTable($table)) {
                $schema->dropTable($table);
            }
        }
    }
}
