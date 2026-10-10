<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Coupon;
use Tueen\Database\Entity\Order;
use Tueen\Database\Enum\CouponType;
use Tueen\Database\Enum\OrderStatus;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Order>
 */
class OrderRepository extends EntityRepository
{
    public string $defaultCurrency = 'XTR';

    public function createOrder(
        string $type,
        Chat|int|null $chat = null,
        string $subtotal = '0.000000000000000000',
        ?string $currency = null,
        OrderStatus $status = OrderStatus::Pending,
    ): Order {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));
        $chatEntity = null;
        if ($chat !== null) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
        }

        $order = new Order(
            type: $type,
            chat: $chatEntity,
            currency: $currency,
            subtotalAmount: $subtotal,
            status: $status,
        );

        $em->persist($order);
        $em->flush();

        return $order;
    }

    public function markPaid(
        Order|int $order,
        ?string $transactionId = null,
        ?string $referenceId = null,
    ): Order {
        $em = $this->getEntityManager();
        $orderEntity = $order instanceof Order ? $order : $this->find($order);

        if ($orderEntity === null) {
            throw RecordNotFoundException::forEntity(Order::class, is_int($order) ? $order : $order->id);
        }

        $orderEntity->markAsPaid($transactionId, $referenceId);

        // Record coupon usage if applied and order has a chat
        if ($orderEntity->coupon !== null && $orderEntity->chat !== null) {
            /** @var CouponRepository $couponRepo */
            $couponRepo = $em->getRepository(Coupon::class);
            $couponRepo->recordUsage(
                coupon: $orderEntity->coupon,
                chat: $orderEntity->chat,
                order: $orderEntity,
                discountAmount: $orderEntity->discountAmount,
            );
        }

        $em->flush();

        return $orderEntity;
    }

    public function applyCoupon(Order $order, Coupon|string $coupon): Order
    {
        $em = $this->getEntityManager();
        /** @var CouponRepository $couponRepo */
        $couponRepo = $em->getRepository(Coupon::class);
        $couponEntity = $coupon instanceof Coupon
            ? $coupon
            : $couponRepo->findOneBy(['code' => strtoupper(trim($coupon))]);

        if ($couponEntity === null || !$couponEntity->isValidNow) {
            throw new \InvalidArgumentException('Invalid or expired coupon provided.');
        }

        if ($order->chat !== null && !$couponRepo->canChatUseCoupon($couponEntity, $order->chat, $order->subtotalAmount, $order->type)) {
            throw new \InvalidArgumentException('Chat is not eligible to use this coupon or usage limits exceeded.');
        }

        if ($couponEntity->currency !== null && $couponEntity->currency !== $order->currency) {
            throw new \InvalidArgumentException("Coupon currency [{$couponEntity->currency}] does not match order currency [{$order->currency}].");
        }

        $order->coupon = $couponEntity;
        $order->couponCode = $couponEntity->code;

        // Calculate discount
        if ($couponEntity->type === CouponType::Percent) {
            $pct = bcdiv($couponEntity->value, '100', 18);
            $calcDiscount = bcmul($order->subtotalAmount, $pct, 18);
        } else {
            $calcDiscount = $couponEntity->value;
        }

        $order->discountAmount = bccomp($calcDiscount, $order->subtotalAmount, 18) > 0 ? $order->subtotalAmount : $calcDiscount;

        $order->couponSnapshot = [
            'code' => $couponEntity->code,
            'title' => $couponEntity->title,
            'type' => $couponEntity->type->value,
            'value' => $couponEntity->value,
        ];

        $em->flush();

        return $order;
    }

    public function getMeta(Order|int $order, string $key, mixed $default = null): mixed
    {
        $orderEntity = $order instanceof Order ? $order : $this->find($order);
        return $orderEntity?->getMeta($key, $default) ?? $default;
    }

    public function setMeta(Order|int $order, string $key, mixed $value, ?string $group = null): \Tueen\Database\Entity\OrderMeta
    {
        $em = $this->getEntityManager();
        $orderEntity = $order instanceof Order ? $order : $this->find($order);
        if ($orderEntity === null) {
            throw RecordNotFoundException::forEntity(Order::class, is_int($order) ? $order : $order->id);
        }

        $meta = $orderEntity->setMeta($key, $value, $group);
        $em->persist($meta);
        $em->flush();

        return $meta;
    }

    public function deleteMeta(Order|int $order, string $key): bool
    {
        $em = $this->getEntityManager();
        $orderEntity = $order instanceof Order ? $order : $this->find($order);
        if ($orderEntity === null) {
            return false;
        }

        $deleted = $orderEntity->deleteMeta($key);
        if ($deleted) {
            $em->flush();
        }

        return $deleted;
    }

    public function hasMeta(Order|int $order, string $key, ?string $group = null): bool
    {
        $orderEntity = $order instanceof Order ? $order : $this->find($order);
        return $orderEntity?->hasMeta($key, $group) ?? false;
    }
}
