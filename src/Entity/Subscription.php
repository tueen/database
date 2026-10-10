<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\SubscriptionRepository;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ORM\Index(name: 'idx_subscriptions_chat_expires', columns: ['chat_id', 'expires_at'])]
#[ORM\Index(name: 'idx_subscriptions_expires', columns: ['expires_at'])]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    public private(set) string $type;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'total_added_seconds', type: Types::BIGINT)]
    public int $totalAddedSeconds = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public bool $isActive {
        get => $this->expiresAt > new DateTimeImmutable();
    }

    public int $remainingSeconds {
        get => max(0, $this->expiresAt->getTimestamp() - time());
    }

    public function __construct(
        Chat $chat,
        string $type = 'vip',
        ?DateTimeImmutable $expiresAt = null,
    ) {
        $this->chat = $chat;
        $this->type = strtolower(trim($type));
        $now = new DateTimeImmutable();
        $this->expiresAt = $expiresAt ?? $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addSeconds(int $seconds): void
    {
        $base = $this->isActive ? $this->expiresAt : new DateTimeImmutable();
        $this->expiresAt = $base->modify("+{$seconds} seconds");
        $this->totalAddedSeconds += $seconds;
        $this->updatedAt = new DateTimeImmutable();
    }
}
