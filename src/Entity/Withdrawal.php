<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Enum\WithdrawalStatus;
use Tueen\Database\Repository\WithdrawalRepository;

#[ORM\Entity(repositoryClass: WithdrawalRepository::class)]
#[ORM\Table(name: 'withdrawals')]
#[ORM\Index(name: 'idx_withdrawals_status_created', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_withdrawals_chat_created_at', columns: ['chat_id', 'created_at'])]
#[ORM\Index(name: 'idx_withdrawals_currency', columns: ['currency'])]
#[ORM\HasLifecycleCallbacks]
class Withdrawal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: WithdrawalStatus::class)]
    public WithdrawalStatus $status = WithdrawalStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    public ?Chat $chat = null;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'processed_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Chat $processedBy = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    public ?string $currency = 'XTR';

    #[ORM\Column(name: 'commission_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $commissionRate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $amount = '0.000000000000000000';

    #[ORM\Column(name: 'fee_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $feeAmount = '0.000000000000000000';

    #[ORM\Column(name: 'payout_method', type: Types::STRING, length: 32, nullable: true)]
    public ?string $payoutMethod = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $destination = null;

    #[ORM\Column(name: 'transaction_id', type: Types::STRING, length: 255, nullable: true)]
    public ?string $transactionId = null;

    #[ORM\Column(name: 'reference_id', type: Types::STRING, length: 255, nullable: true)]
    public ?string $referenceId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(name: 'admin_note', type: Types::TEXT, nullable: true)]
    public ?string $adminNote = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'processed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $processedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @var Collection<string, WithdrawalMeta>
     */
    #[ORM\OneToMany(targetEntity: WithdrawalMeta::class, mappedBy: 'withdrawal', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $meta;

    public string $netIncome {
        get => bcsub($this->amount, $this->feeAmount, 18);
    }

    public function __construct(
        ?Chat $chat,
        string $amount,
        ?string $currency = 'XTR',
        string $feeAmount = '0.000000000000000000',
        string $commissionRate = '0.00',
        ?string $payoutMethod = null,
        ?string $destination = null,
    ) {
        $this->chat = $chat;
        $this->amount = $amount;
        $this->currency = $currency !== null ? strtoupper(trim($currency)) : null;
        $this->feeAmount = $feeAmount;
        $this->commissionRate = $commissionRate;
        $this->payoutMethod = $payoutMethod;
        $this->destination = $destination;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->meta = new ArrayCollection();
    }

    #[ORM\ManyToOne(targetEntity: WalletTransaction::class)]
    #[ORM\JoinColumn(name: 'wallet_transaction_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?WalletTransaction $walletTransaction = null;

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        foreach ($this->meta as $metaItem) {
            if ($metaItem->metaKey === $key) {
                return $metaItem->value;
            }
        }

        return $default;
    }

    public function setMeta(string $key, mixed $value, ?string $group = null): WithdrawalMeta
    {
        foreach ($this->meta as $metaItem) {
            if ($metaItem->metaKey === $key) {
                $metaItem->value = $value;
                if ($group !== null) {
                    $metaItem->group = $group;
                }
                return $metaItem;
            }
        }

        $metaItem = new WithdrawalMeta($this, $key, $value, $group);
        $this->meta->add($metaItem);
        return $metaItem;
    }

    public function deleteMeta(string $key): bool
    {
        foreach ($this->meta as $index => $metaItem) {
            if ($metaItem->metaKey === $key) {
                $this->meta->remove($index);
                return true;
            }
        }

        return false;
    }

    public function hasMeta(string $key, ?string $group = null): bool
    {
        foreach ($this->meta as $metaItem) {
            if ($metaItem->metaKey === $key) {
                if ($group !== null && $metaItem->group !== $group) {
                    continue;
                }
                return true;
            }
        }

        return false;
    }
}
