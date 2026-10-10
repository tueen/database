<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Subscription;
use Tueen\Database\Entity\SubscriptionTransaction;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Subscription>
 */
class SubscriptionRepository extends EntityRepository
{
    public function getActive(Chat|int $chat, string $type = 'vip'): ?Subscription
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $type = strtolower(trim($type));

        $sub = $this->findOneBy(['chat' => $chatId, 'type' => $type]);
        if ($sub === null || !$sub->isActive) {
            return null;
        }

        return $sub;
    }

    public function hasActive(Chat|int $chat, string $type = 'vip'): bool
    {
        return $this->getActive($chat, $type) !== null;
    }

    public function addTime(
        Chat|int $chat,
        int $seconds,
        string $type = 'vip',
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): Subscription {
        $em = $this->getEntityManager();
        $type = strtolower(trim($type));

        return $em->wrapInTransaction(function () use ($em, $chat, $seconds, $type, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $sub = $this->findOneBy(['chat' => $chatEntity->id, 'type' => $type]);
            $isNew = false;

            if ($sub === null) {
                $sub = new Subscription($chatEntity, $type);
                $em->persist($sub);
                $isNew = true;
            }

            $beforeExpiresAt = $sub->expiresAt;
            $beforeAdded = $sub->totalAddedSeconds;

            $sub->addSeconds($seconds);

            $afterExpiresAt = $sub->expiresAt;
            $afterAdded = $sub->totalAddedSeconds;

            $trans = new SubscriptionTransaction(
                chat: $chatEntity,
                type: $type,
                expiresAtBefore: $isNew ? null : $beforeExpiresAt,
                expiresAtAfter: $afterExpiresAt,
                totalAddedSecondsBefore: $beforeAdded,
                totalAddedSecondsAfter: $afterAdded,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($trans);
            $em->flush();

            return $sub;
        });
    }
}
