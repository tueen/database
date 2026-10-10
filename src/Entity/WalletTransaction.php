<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'wallet_transactions')]
#[ORM\UniqueConstraint(name: 'uk_wallet_trans_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_wallet_trans_chat_curr_created', columns: ['chat_id', 'currency', 'created_at'])]
#[ORM\Index(name: 'idx_wallet_trans_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_wallet_trans_created_by', columns: ['created_by'])]
class WalletTransaction
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

    #[ORM\Column(type: Types::STRING, length: 10)]
    public private(set) string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 36, scale: 18)]
    public private(set) string $amount;

    #[ORM\Column(name: 'balance_before', type: Types::DECIMAL, precision: 36, scale: 18)]
    public private(set) string $balanceBefore;

    #[ORM\Column(name: 'balance_after', type: Types::DECIMAL, precision: 36, scale: 18)]
    public private(set) string $balanceAfter;

    #[ORM\Column(name: 'total_consumed_before', type: Types::DECIMAL, precision: 40, scale: 18)]
    public private(set) string $totalConsumedBefore;

    #[ORM\Column(name: 'total_consumed_after', type: Types::DECIMAL, precision: 40, scale: 18)]
    public private(set) string $totalConsumedAfter;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    public ?string $source = null;

    #[ORM\Column(name: 'source_id', type: Types::BIGINT, nullable: true)]
    public ?int $sourceId = null;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Chat $createdBy = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public bool $isCredit {
        get => bccomp($this->amount, '0', 18) > 0;
    }

    public bool $isDebit {
        get => bccomp($this->amount, '0', 18) < 0;
    }

    public function __construct(
        Chat $chat,
        string $currency,
        string $amount,
        string $balanceBefore,
        string $balanceAfter,
        string $totalConsumedBefore = '0.000000000000000000',
        string $totalConsumedAfter = '0.000000000000000000',
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
        ?string $publicId = null,
    ) {
        $this->chat = $chat;
        $this->currency = strtoupper(trim($currency));
        $this->amount = $amount;
        $this->balanceBefore = $balanceBefore;
        $this->balanceAfter = $balanceAfter;
        $this->totalConsumedBefore = $totalConsumedBefore;
        $this->totalConsumedAfter = $totalConsumedAfter;
        $this->source = $source;
        $this->sourceId = $sourceId;
        $this->createdBy = $createdBy;
        $this->publicId = $publicId ?? Ulid::generate();
        $this->createdAt = new DateTimeImmutable();
    }
}
