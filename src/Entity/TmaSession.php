<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Tueen\Database\Repository\TmaSessionRepository;

#[ORM\Entity(repositoryClass: TmaSessionRepository::class)]
#[ORM\Table(name: 'tma_sessions')]
#[ORM\UniqueConstraint(name: 'uk_tma_sessions_token', columns: ['token'])]
#[ORM\UniqueConstraint(name: 'uk_tma_sessions_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_tma_sessions_chat_expires', columns: ['chat_id', 'expires_at'])]
#[ORM\Index(name: 'idx_tma_sessions_is_revoked', columns: ['is_revoked'])]
#[ORM\Index(name: 'idx_tma_sessions_expires_at', columns: ['expires_at'])]
#[ORM\HasLifecycleCallbacks]
class TmaSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\Column(name: 'token', type: Types::STRING, length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 32, max: 64)]
    public private(set) string $token;

    #[ORM\Column(name: 'query_id', type: Types::STRING, length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    public ?string $queryId = null;

    #[ORM\Column(name: 'start_param', type: Types::STRING, length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    public ?string $startParam = null;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45, nullable: true)]
    #[Assert\Length(max: 45)]
    public ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 512, nullable: true)]
    #[Assert\Length(max: 512)]
    public ?string $userAgent = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'payload', type: Types::JSON, nullable: true)]
    public ?array $payload = null;

    #[ORM\Column(name: 'is_revoked', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isRevoked = false;

    #[ORM\Column(name: 'auth_date', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $authDate = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_active_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $lastActiveAt;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $expiresAt;

    // PHP 8.4 Virtual Property Hooks
    public bool $isExpired {
        get => $this->expiresAt <= new DateTimeImmutable();
    }

    public bool $isValid {
        get => !$this->isRevoked && !$this->isExpired;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        Chat $chat,
        string $token,
        DateTimeImmutable $expiresAt,
        ?string $queryId = null,
        ?string $startParam = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $payload = null,
        ?DateTimeImmutable $authDate = null,
        ?string $publicId = null,
    ) {
        $this->chat = $chat;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->queryId = $queryId;
        $this->startParam = $startParam;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->payload = $payload;
        $this->authDate = $authDate;
        $this->publicId = $publicId ?? Ulid::generate();
        $this->isRevoked = false;

        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->lastActiveAt = $now;
    }

    public function touch(): void
    {
        $this->lastActiveAt = new DateTimeImmutable();
    }

    public function revoke(): void
    {
        $this->isRevoked = true;
        $this->lastActiveAt = new DateTimeImmutable();
    }
}
