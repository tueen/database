<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Coupon;
use Tueen\Database\Entity\CouponMeta;
use Tueen\Database\Entity\CouponUsage;
use Tueen\Database\Entity\Order;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Coupon>
 */
class CouponRepository extends EntityRepository
{
    public function findByCode(string $code): ?Coupon
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    public function findValidByCode(string $code): ?Coupon
    {
        $coupon = $this->findByCode($code);
        if ($coupon === null || !$coupon->isValidNow) {
            return null;
        }

        return $coupon;
    }

    public function canChatUseCoupon(
        Coupon $coupon,
        Chat|int $chat,
        ?string $orderSubtotal = null,
        ?string $orderType = null,
    ): bool {
        if (!$coupon->isValidNow) {
            return false;
        }

        $chatId = $chat instanceof Chat ? $chat->id : $chat;

        // Check allowed users list if configured
        if ($coupon->allowedUsers !== null && !in_array($chatId, $coupon->allowedUsers, true)) {
            return false;
        }

        // Check allowed order types if configured
        if ($orderType !== null && $coupon->allowedOrderTypes !== null && !in_array($orderType, $coupon->allowedOrderTypes, true)) {
            return false;
        }

        // Check min order amount if configured
        if ($orderSubtotal !== null && $coupon->minOrderAmount !== null) {
            if (bccomp($orderSubtotal, $coupon->minOrderAmount, 18) < 0) {
                return false;
            }
        }

        $em = $this->getEntityManager();

        // Check global max uses
        if ($coupon->maxUses !== null) {
            $totalUsages = (int) $em->getRepository(CouponUsage::class)
                ->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.coupon = :coupon')
                ->andWhere('u.reverted = false')
                ->setParameter('coupon', $coupon)
                ->getQuery()
                ->getSingleScalarResult();

            if ($totalUsages >= $coupon->maxUses) {
                return false;
            }
        }

        // Check per-user max uses
        if ($coupon->maxUsesPerUser !== null) {
            $userUsages = (int) $em->getRepository(CouponUsage::class)
                ->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.coupon = :coupon')
                ->andWhere('u.chat = :chatId')
                ->andWhere('u.reverted = false')
                ->setParameter('coupon', $coupon)
                ->setParameter('chatId', $chatId)
                ->getQuery()
                ->getSingleScalarResult();

            if ($userUsages >= $coupon->maxUsesPerUser) {
                return false;
            }
        }

        return true;
    }

    public function recordUsage(
        Coupon $coupon,
        Chat $chat,
        Order $order,
        string $discountAmount = '0.000000000000000000',
    ): CouponUsage {
        $em = $this->getEntityManager();
        $usage = new CouponUsage($coupon, $chat, $order, $discountAmount);
        $em->persist($usage);
        $em->flush();

        return $usage;
    }

    public function revertUsage(Order|int $order): bool
    {
        $em = $this->getEntityManager();
        $orderId = $order instanceof Order ? $order->id : $order;
        /** @var CouponUsage|null $usage */
        $usage = $em->getRepository(CouponUsage::class)->findOneBy(['order' => $orderId]);
        if ($usage === null || $usage->reverted) {
            return false;
        }

        $usage->revert();
        $em->flush();

        return true;
    }

    public function getMeta(Coupon|int $coupon, string $key, mixed $default = null): mixed
    {
        $couponEntity = $coupon instanceof Coupon ? $coupon : $this->find($coupon);
        return $couponEntity?->getMeta($key, $default) ?? $default;
    }

    public function setMeta(Coupon|int $coupon, string $key, mixed $value, ?string $group = null): CouponMeta
    {
        $em = $this->getEntityManager();
        $couponEntity = $coupon instanceof Coupon ? $coupon : $this->find($coupon);
        if ($couponEntity === null) {
            throw RecordNotFoundException::forEntity(Coupon::class, is_int($coupon) ? $coupon : $coupon->id);
        }

        $meta = $couponEntity->setMeta($key, $value, $group);
        $em->persist($meta);
        $em->flush();

        return $meta;
    }

    public function deleteMeta(Coupon|int $coupon, string $key): bool
    {
        $em = $this->getEntityManager();
        $couponEntity = $coupon instanceof Coupon ? $coupon : $this->find($coupon);
        if ($couponEntity === null) {
            return false;
        }

        $deleted = $couponEntity->deleteMeta($key);
        if ($deleted) {
            $em->flush();
        }

        return $deleted;
    }

    public function hasMeta(Coupon|int $coupon, string $key, ?string $group = null): bool
    {
        $couponEntity = $coupon instanceof Coupon ? $coupon : $this->find($coupon);
        return $couponEntity?->hasMeta($key, $group) ?? false;
    }
}
