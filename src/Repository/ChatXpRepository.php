<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\ChatXp;
use Tueen\Database\Entity\ChatXpTransaction;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<ChatXp>
 */
class ChatXpRepository extends EntityRepository
{
    public function getXp(Chat|int $chat): int
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $chatXp = $this->find($chatId);

        return $chatXp !== null ? $chatXp->xp : 0;
    }

    public function addXp(
        Chat|int $chat,
        int $amount,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): ChatXpTransaction {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($em, $chat, $amount, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $chatXp = $this->find($chatEntity->id);
            if ($chatXp === null) {
                $chatXp = new ChatXp($chatEntity, 0);
                $em->persist($chatXp);
            }

            $before = $chatXp->xp;
            $after = $before + $amount;
            $chatXp->xp = $after;

            $tx = new ChatXpTransaction(
                chat: $chatEntity,
                value: $amount,
                xpBefore: $before,
                xpAfter: $after,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($tx);
            $em->flush();

            return $tx;
        });
    }

    public function setXp(
        Chat|int $chat,
        int $newXp,
        ?string $source = 'set_xp',
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): ChatXpTransaction {
        $em = $this->getEntityManager();

        return $em->wrapInTransaction(function () use ($em, $chat, $newXp, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $chatXp = $this->find($chatEntity->id);
            if ($chatXp === null) {
                $chatXp = new ChatXp($chatEntity, 0);
                $em->persist($chatXp);
            }

            $before = $chatXp->xp;
            $diff = $newXp - $before;
            $chatXp->xp = $newXp;

            $tx = new ChatXpTransaction(
                chat: $chatEntity,
                value: $diff,
                xpBefore: $before,
                xpAfter: $newXp,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($tx);
            $em->flush();

            return $tx;
        });
    }

    /**
     * @return list<ChatXp>
     */
    public function getLeaderboard(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('x')
            ->orderBy('x.xp', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
