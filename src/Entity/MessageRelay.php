<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Tueen\Database\Repository\MessageRelayRepository::class)]
#[ORM\Table(name: 'message_relays')]
#[ORM\UniqueConstraint(name: 'uk_message_relays_tgt', columns: ['chat_id2', 'message_id2'])]
#[ORM\Index(name: 'idx_message_relays_chat_ids', columns: ['chat_id1', 'chat_id2'])]
#[ORM\HasLifecycleCallbacks]
class MessageRelay
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id1', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat1;

    #[ORM\Id]
    #[ORM\Column(name: 'message_id1', type: Types::BIGINT)]
    public private(set) int $messageId1;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id2', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat2;

    #[ORM\Column(name: 'message_id2', type: Types::BIGINT)]
    public int $messageId2;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $message = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed>|null $message
     */
    public function __construct(
        Chat $chat1,
        int $messageId1,
        Chat $chat2,
        int $messageId2,
        ?array $message = null,
    ) {
        $this->chat1 = $chat1;
        $this->messageId1 = $messageId1;
        $this->chat2 = $chat2;
        $this->messageId2 = $messageId2;
        $this->message = $message;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
