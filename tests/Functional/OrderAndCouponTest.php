<?php

declare(strict_types=1);

namespace Tueen\Database\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\DatabaseManager;
use Tueen\Database\Entity\Coupon;
use Tueen\Database\Enum\CouponType;
use Tueen\Database\Enum\OrderStatus;

final class OrderAndCouponTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = DatabaseManager::create(DatabaseConfig::createSqlite(':memory:'));
    }

    public function testOrderLifecycleAndCouponApplication(): void
    {
        $chat = $this->db->chats()->findOrCreate(7001, 'shopper');

        // Create Coupon (20% off)
        $coupon = new Coupon(
            code: 'TUEEN20',
            title: '20% Welcome Discount',
            type: CouponType::Percent,
            value: '20.000000000000000000',
        );
        $this->db->getEntityManager()->persist($coupon);
        $this->db->getEntityManager()->flush();

        // Create Order for 100 TON
        $order = $this->db->orders()->createOrder('vip_sub', $chat, '100.000000000000000000', 'TON');
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('100.000000000000000000', $order->totalAmount);

        // Apply Coupon
        $this->db->orders()->applyCoupon($order, 'TUEEN20');
        $this->assertSame('20.000000000000000000', $order->discountAmount);
        $this->assertSame('80.000000000000000000', $order->totalAmount);

        // Mark as Paid
        $this->db->orders()->markPaid($order, 'tx_ton_999', 'ref_ton_888');
        $this->assertTrue($order->isPaid);
        $this->assertSame('80.000000000000000000', $order->paidAmount);
        $this->assertSame('tx_ton_999', $order->transactionId);

        // Verify CouponUsage record was created
        $usages = $this->db->getEntityManager()->getRepository(\Tueen\Database\Entity\CouponUsage::class)->findBy([
            'coupon' => $coupon->id,
            'order' => $order->id,
        ]);
        $this->assertCount(1, $usages);
        $this->assertSame('20.000000000000000000', $usages[0]->discountAmount);
    }

    public function testCouponMaxUsesValidation(): void
    {
        $chat = $this->db->chats()->findOrCreate(7002, 'shopper2');

        $coupon = new Coupon(
            code: 'LIMITED',
            title: 'Limited Coupon',
            type: CouponType::Fixed,
            value: '10.000000000000000000',
            maxUses: 1,
            maxUsesPerUser: 1,
        );
        $this->db->getEntityManager()->persist($coupon);
        $this->db->getEntityManager()->flush();

        $this->assertTrue($this->db->coupons()->canChatUseCoupon($coupon, $chat));

        // Create and pay an order with this coupon
        $order = $this->db->orders()->createOrder('shop_item', $chat, '50.000000000000000000', 'TON');
        $this->db->orders()->applyCoupon($order, 'LIMITED');
        $this->db->orders()->markPaid($order);

        // Refresh coupon
        $this->db->getEntityManager()->refresh($coupon);
        $this->assertSame(1, $coupon->usedCount);

        // Now user cannot use it again
        $this->assertFalse($this->db->coupons()->canChatUseCoupon($coupon, $chat));

        // Reverting usage restores eligibility
        $this->assertTrue($this->db->coupons()->revertUsage($order));
        $this->db->getEntityManager()->refresh($coupon);
        $this->assertSame(0, $coupon->usedCount);
        $this->assertTrue($this->db->coupons()->canChatUseCoupon($coupon, $chat));
    }

    public function testOrderAndCouponMeta(): void
    {
        $chat = $this->db->chats()->findOrCreate(7003, 'meta_shopper');
        $order = $this->db->orders()->createOrder('goods', $chat, '25.000000000000000000');

        $this->db->orders()->setMeta($order, 'shipping_tracking', 'TRK123456');
        $this->assertSame('TRK123456', $this->db->orders()->getMeta($order, 'shipping_tracking'));
        $this->assertTrue($this->db->orders()->hasMeta($order, 'shipping_tracking'));
        $this->assertTrue($this->db->orders()->deleteMeta($order, 'shipping_tracking'));
        $this->assertNull($this->db->orders()->getMeta($order, 'shipping_tracking'));

        $coupon = new Coupon('PROMO10', 'Promo', CouponType::Percent, '10.000000000000000000');
        $this->db->getEntityManager()->persist($coupon);
        $this->db->getEntityManager()->flush();

        $this->db->coupons()->setMeta($coupon, 'campaign', 'summer_sale');
        $this->assertSame('summer_sale', $this->db->coupons()->getMeta($coupon, 'campaign'));
        $this->assertTrue($this->db->coupons()->hasMeta($coupon, 'campaign'));
        $this->assertTrue($this->db->coupons()->deleteMeta($coupon, 'campaign'));
        $this->assertNull($this->db->coupons()->getMeta($coupon, 'campaign'));
    }
}
