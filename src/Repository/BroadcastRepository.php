<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\BroadcastMessage;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Enum\BroadcastStatus;
use Tueen\Database\Enum\ChatType;

/**
 * @extends EntityRepository<BroadcastMessage>
 */
class BroadcastRepository extends EntityRepository
{
    /**
     * @return list<Chat>
     */
    public function fetchNextPendingBatch(BroadcastMessage $broadcast, int $batchSize = 100): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('c')
            ->from(Chat::class, 'c')
            ->where('c.accessible = true')
            ->andWhere('c.banned = false');

        if ($broadcast->lastOffset !== null) {
            $qb->andWhere('c.id > :lastOffset')
                ->setParameter('lastOffset', $broadcast->lastOffset);
        }

        $qb->orderBy('c.id', 'ASC')
            ->setMaxResults($batchSize);

        $allowedTypes = [];
        if ($broadcast->allowPrivateChats) {
            $allowedTypes[] = ChatType::Private;
        }
        if ($broadcast->allowGroupChats) {
            $allowedTypes[] = ChatType::Group;
        }
        if ($broadcast->allowSupergroupChats) {
            $allowedTypes[] = ChatType::Supergroup;
        }
        if ($broadcast->allowChannelChats) {
            $allowedTypes[] = ChatType::Channel;
        }

        if (count($allowedTypes) === 0) {
            return [];
        }

        $qb->andWhere('c.type IN (:types)')
            ->setParameter('types', $allowedTypes);

        if ($broadcast->excludedChatIds !== null && count($broadcast->excludedChatIds) > 0) {
            $qb->andWhere('c.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $broadcast->excludedChatIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function countTargetChats(BroadcastMessage $broadcast): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('COUNT(c.id)')
            ->from(Chat::class, 'c')
            ->where('c.accessible = true')
            ->andWhere('c.banned = false');

        $allowedTypes = [];
        if ($broadcast->allowPrivateChats) {
            $allowedTypes[] = ChatType::Private;
        }
        if ($broadcast->allowGroupChats) {
            $allowedTypes[] = ChatType::Group;
        }
        if ($broadcast->allowSupergroupChats) {
            $allowedTypes[] = ChatType::Supergroup;
        }
        if ($broadcast->allowChannelChats) {
            $allowedTypes[] = ChatType::Channel;
        }

        if (count($allowedTypes) === 0) {
            return 0;
        }

        $qb->andWhere('c.type IN (:types)')
            ->setParameter('types', $allowedTypes);

        if ($broadcast->excludedChatIds !== null && count($broadcast->excludedChatIds) > 0) {
            $qb->andWhere('c.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $broadcast->excludedChatIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function updateProgress(
        BroadcastMessage $broadcast,
        int $lastChatId,
        int $sentIncrement = 0,
        int $failedIncrement = 0,
        int $ignoredIncrement = 0,
    ): void {
        $broadcast->lastOffset = $lastChatId;
        $broadcast->sentCount += $sentIncrement;
        $broadcast->failedCount += $failedIncrement;
        $broadcast->ignoredCount += $ignoredIncrement;
        if ($sentIncrement > 0) {
            $broadcast->lastSentAt = new DateTimeImmutable();
        }

        if ($broadcast->initialChatCount > 0 && ($broadcast->sentCount + $broadcast->failedCount + $broadcast->ignoredCount) >= $broadcast->initialChatCount) {
            $broadcast->status = BroadcastStatus::Completed;
        }

        $this->getEntityManager()->flush();
    }
}
