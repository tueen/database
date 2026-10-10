<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Enum\BroadcastStatus;
use Tueen\Database\Repository\BroadcastRepository;

#[ORM\Entity(repositoryClass: BroadcastRepository::class)]
#[ORM\Table(name: 'broadcast_messages')]
#[ORM\Index(name: 'idx_broadcast_messages_source_chat', columns: ['source_chat_id'])]
#[ORM\Index(name: 'idx_broadcast_messages_status', columns: ['status'])]
#[ORM\Index(name: 'idx_broadcast_messages_priority', columns: ['priority'])]
#[ORM\HasLifecycleCallbacks]
class BroadcastMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BroadcastStatus::class)]
    public BroadcastStatus $status = BroadcastStatus::Draft;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'source_chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $sourceChat;

    #[ORM\Column(name: 'source_message_id', type: Types::BIGINT)]
    public private(set) int $sourceMessageId;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    public int $priority = 0;

    #[ORM\Column(name: 'allow_private_chats', type: Types::BOOLEAN, options: ['default' => true])]
    public bool $allowPrivateChats = true;

    #[ORM\Column(name: 'allow_group_chats', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $allowGroupChats = false;

    #[ORM\Column(name: 'allow_supergroup_chats', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $allowSupergroupChats = false;

    #[ORM\Column(name: 'allow_channel_chats', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $allowChannelChats = false;

    /**
     * @var list<int>|null
     */
    #[ORM\Column(name: 'excluded_chat_ids', type: Types::JSON, nullable: true)]
    public ?array $excludedChatIds = null;

    #[ORM\Column(name: 'last_offset', type: Types::BIGINT, nullable: true)]
    public ?int $lastOffset = null;

    #[ORM\Column(name: 'initial_chat_count', type: Types::BIGINT, options: ['default' => 0])]
    public int $initialChatCount = 0;

    #[ORM\Column(name: 'sent_count', type: Types::BIGINT, options: ['default' => 0])]
    public int $sentCount = 0;

    #[ORM\Column(name: 'failed_count', type: Types::BIGINT, options: ['default' => 0])]
    public int $failedCount = 0;

    #[ORM\Column(name: 'ignored_count', type: Types::BIGINT, options: ['default' => 0])]
    public int $ignoredCount = 0;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $lastSentAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public float $progressPercentage {
        get {
            if ($this->initialChatCount <= 0) {
                return 0.0;
            }
            $totalProcessed = $this->sentCount + $this->failedCount + $this->ignoredCount;
            return round(min(100.0, ($totalProcessed / $this->initialChatCount) * 100), 2);
        }
    }

    public bool $isFinished {
        get => $this->status->isTerminal();
    }

    public function __construct(
        Chat $sourceChat,
        int $sourceMessageId,
        BroadcastStatus $status = BroadcastStatus::Draft,
        int $priority = 0,
        bool $allowPrivateChats = true,
        bool $allowGroupChats = false,
        bool $allowSupergroupChats = false,
        bool $allowChannelChats = false,
    ) {
        $this->sourceChat = $sourceChat;
        $this->sourceMessageId = $sourceMessageId;
        $this->status = $status;
        $this->priority = $priority;
        $this->allowPrivateChats = $allowPrivateChats;
        $this->allowGroupChats = $allowGroupChats;
        $this->allowSupergroupChats = $allowSupergroupChats;
        $this->allowChannelChats = $allowChannelChats;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordSent(): void
    {
        $this->sentCount++;
        $this->lastSentAt = new DateTimeImmutable();
    }

    public function recordFailed(): void
    {
        $this->failedCount++;
    }

    public function recordIgnored(): void
    {
        $this->ignoredCount++;
    }
}
