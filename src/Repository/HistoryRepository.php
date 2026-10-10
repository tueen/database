<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\History;

/**
 * @extends EntityRepository<History>
 */
class HistoryRepository extends EntityRepository
{
    /**
     * @param array<string, mixed>|null $extra
     */
    public function log(
        string $targetType,
        int $targetId,
        string $action,
        ?int $chatId = null,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?string $requestId = null,
        ?array $extra = null,
    ): History {
        $history = new History(
            targetType: $targetType,
            targetId: $targetId,
            action: $action,
            chatId: $chatId,
            fieldName: $fieldName,
            oldValue: $oldValue,
            newValue: $newValue,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            requestId: $requestId,
            extra: $extra,
        );

        $em = $this->getEntityManager();
        $em->persist($history);
        $em->flush();

        return $history;
    }

    /**
     * @return list<History>
     */
    public function getTimeline(string $targetType, int $targetId, int $limit = 50): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.targetType = :type')
            ->andWhere('h.targetId = :id')
            ->setParameter('type', $targetType)
            ->setParameter('id', $targetId)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
