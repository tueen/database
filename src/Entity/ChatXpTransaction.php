<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'chat_xp_transactions')]
#[ORM\UniqueConstraint(name: 'uk_chat_xp_trans_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_chat_xp_trans_chat_id', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_chat_xp_trans_created_by', columns: ['created_by'])]
#[ORM\HasLifecycleCallbacks]
class ChatXpTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    public private(set) Chat $chat;

    #[ORM\Column(type: Types::BIGINT)]
    public private(set) int $value;

    #[ORM\Column(name: 'xp_before', type: Types::BIGINT)]
    public private(set) int $xpBefore;

    #[ORM\Column(name: 'xp_after', type: Types::BIGINT)]
    public private(set) int $xpAfter;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $source = null;

    #[ORM\Column(name: 'source_id', type: Types::BIGINT, nullable: true)]
    public ?int $sourceId = null;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Chat $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct(
        Chat $chat,
        int $value,
        int $xpBefore,
        int $xpAfter,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
        ?string $publicId = null,
    ) {
        $this->chat = $chat;
        $this->value = $value;
        $this->xpBefore = $xpBefore;
        $this->xpAfter = $xpAfter;
        $this->source = $source;
        $this->sourceId = $sourceId;
        $this->createdBy = $createdBy;
        $this->publicId = $publicId ?? Ulid::generate();
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
