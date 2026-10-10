<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\MessageRelay;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<MessageRelay>
 */
class MessageRelayRepository extends EntityRepository
{
    /**
     * @param array<string, mixed>|null $message
     */
    public function relay(
        Chat|int $chat1,
        int $messageId1,
        Chat|int $chat2,
        int $messageId2,
        ?array $message = null,
    ): MessageRelay {
        $em = $this->getEntityManager();
        $chat1Entity = $chat1 instanceof Chat ? $chat1 : $em->find(Chat::class, $chat1);
        $chat2Entity = $chat2 instanceof Chat ? $chat2 : $em->find(Chat::class, $chat2);

        if ($chat1Entity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat1) ? $chat1 : $chat1->id);
        }
        if ($chat2Entity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat2) ? $chat2 : $chat2->id);
        }

        $relay = new MessageRelay(
            chat1: $chat1Entity,
            messageId1: $messageId1,
            chat2: $chat2Entity,
            messageId2: $messageId2,
            message: $message,
        );

        $em->persist($relay);
        $em->flush();

        return $relay;
    }

    public function findOrigin(Chat|int $targetChat, int $targetMessageId): ?MessageRelay
    {
        $targetChatId = $targetChat instanceof Chat ? $targetChat->id : $targetChat;

        return $this->findOneBy([
            'chat2' => $targetChatId,
            'messageId2' => $targetMessageId,
        ]);
    }

    public function findForward(Chat|int $sourceChat, int $sourceMessageId): ?MessageRelay
    {
        $sourceChatId = $sourceChat instanceof Chat ? $sourceChat->id : $sourceChat;

        return $this->findOneBy([
            'chat1' => $sourceChatId,
            'messageId1' => $sourceMessageId,
        ]);
    }
}
