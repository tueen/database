<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Referral;
use Tueen\Database\Enum\ReferralStatus;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Referral>
 */
class ReferralRepository extends EntityRepository
{
    public function registerReferral(Chat|int $referred, Chat|int $referrer): Referral
    {
        $em = $this->getEntityManager();
        $referredEntity = $referred instanceof Chat ? $referred : $em->find(Chat::class, $referred);
        $referrerEntity = $referrer instanceof Chat ? $referrer : $em->find(Chat::class, $referrer);

        if ($referredEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($referred) ? $referred : $referred->id);
        }
        if ($referrerEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($referrer) ? $referrer : $referrer->id);
        }

        $existing = $this->find($referredEntity->id);
        if ($existing !== null) {
            return $existing;
        }

        $referral = new Referral($referredEntity, $referrerEntity);
        $em->persist($referral);
        $em->flush();

        return $referral;
    }

    public function qualify(Chat|int $referred): Referral
    {
        $referredId = $referred instanceof Chat ? $referred->id : $referred;
        $referral = $this->find($referredId);

        if ($referral === null) {
            throw RecordNotFoundException::forEntity(Referral::class, $referredId);
        }

        $referral->qualify();
        $this->getEntityManager()->flush();

        return $referral;
    }

    public function getReferrer(Chat|int $referred): ?Chat
    {
        $referredId = $referred instanceof Chat ? $referred->id : $referred;
        $referral = $this->find($referredId);

        return $referral?->referrer;
    }

    public function countReferrals(Chat|int $referrer, ?ReferralStatus $status = null): int
    {
        $referrerId = $referrer instanceof Chat ? $referrer->id : $referrer;
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.referred)')
            ->where('r.referrer = :referrerId')
            ->setParameter('referrerId', $referrerId);

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
