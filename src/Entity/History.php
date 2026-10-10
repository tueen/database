<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\HistoryRepository;

#[ORM\Entity(repositoryClass: HistoryRepository::class)]
#[ORM\Table(name: 'histories')]
#[ORM\Index(name: 'idx_histories_target', columns: ['target_type', 'target_id', 'created_at'])]
#[ORM\Index(name: 'idx_histories_chat_created', columns: ['chat_id', 'created_at'])]
#[ORM\Index(name: 'idx_histories_action_created', columns: ['action', 'created_at'])]
#[ORM\Index(name: 'idx_histories_request', columns: ['request_id'])]
class History
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'target_type', type: Types::STRING, length: 64)]
    public private(set) string $targetType;

    #[ORM\Column(name: 'target_id', type: Types::BIGINT)]
    public private(set) int $targetId;

    #[ORM\Column(name: 'chat_id', type: Types::BIGINT, nullable: true)]
    public ?int $chatId = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    public private(set) string $action;

    #[ORM\Column(name: 'field_name', type: Types::STRING, length: 100, nullable: true)]
    public ?string $fieldName = null;

    #[ORM\Column(name: 'old_value', type: Types::TEXT, nullable: true)]
    public ?string $oldValue = null;

    #[ORM\Column(name: 'new_value', type: Types::TEXT, nullable: true)]
    public ?string $newValue = null;

    #[ORM\Column(name: 'old_status', type: Types::STRING, length: 20, nullable: true)]
    public ?string $oldStatus = null;

    #[ORM\Column(name: 'new_status', type: Types::STRING, length: 20, nullable: true)]
    public ?string $newStatus = null;

    #[ORM\Column(name: 'request_id', type: Types::STRING, length: 64, nullable: true)]
    public ?string $requestId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $extra
     */
    public function __construct(
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
    ) {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->action = $action;
        $this->chatId = $chatId;
        $this->fieldName = $fieldName;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->requestId = $requestId;
        $this->extra = $extra;
        $this->createdAt = new DateTimeImmutable();
    }
}
