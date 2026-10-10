<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'subscription_transactions')]
#[ORM\UniqueConstraint(name: 'uk_sub_trans_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_sub_trans_chat_type_source', columns: ['chat_id', 'type', 'source'])]
#[ORM\Index(name: 'idx_sub_trans_created_by', columns: ['created_by'])]
class SubscriptionTransaction
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

    #[ORM\Column(type: Types::STRING, length: 32)]
    public private(set) string $type;

    #[ORM\Column(name: 'expires_at_before', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $expiresAtBefore = null;

    #[ORM\Column(name: 'expires_at_after', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $expiresAtAfter = null;

    #[ORM\Column(name: 'total_added_seconds_before', type: Types::BIGINT)]
    public int $totalAddedSecondsBefore = 0;

    #[ORM\Column(name: 'total_added_seconds_after', type: Types::BIGINT)]
    public int $totalAddedSecondsAfter = 0;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $source = null;

    #[ORM\Column(name: 'source_id', type: Types::BIGINT, nullable: true)]
    public ?int $sourceId = null;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Chat $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public function __construct(
        Chat $chat,
        string $type,
        ?DateTimeImmutable $expiresAtBefore,
        ?DateTimeImmutable $expiresAtAfter,
        int $totalAddedSecondsBefore = 0,
        int $totalAddedSecondsAfter = 0,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
        ?string $publicId = null,
    ) {
        $this->chat = $chat;
        $this->type = strtolower(trim($type));
        $this->expiresAtBefore = $expiresAtBefore;
        $this->expiresAtAfter = $expiresAtAfter;
        $this->totalAddedSecondsBefore = $totalAddedSecondsBefore;
        $this->totalAddedSecondsAfter = $totalAddedSecondsAfter;
        $this->source = $source;
        $this->sourceId = $sourceId;
        $this->createdBy = $createdBy;
        $this->publicId = $publicId ?? Ulid::generate();
        $this->createdAt = new DateTimeImmutable();
    }
}
