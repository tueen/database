<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\WalletRepository;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallet_balances')]
#[ORM\Index(name: 'idx_wallet_balances_currency', columns: ['currency'])]
#[ORM\HasLifecycleCallbacks]
class WalletBalance
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class, inversedBy: 'wallets')]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    public private(set) Chat $chat;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 10)]
    public private(set) string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $balance = '0.000000000000000000' {
        get => bcadd($this->balance, '0', 18);
        set(string $value) => bcadd($value, '0', 18);
    }

    #[ORM\Column(name: 'frozen_balance', type: Types::DECIMAL, precision: 36, scale: 18, options: ['default' => '0.000000000000000000'])]
    public string $frozenBalance = '0.000000000000000000' {
        get => bcadd($this->frozenBalance, '0', 18);
        set(string $value) => bcadd($value, '0', 18);
    }

    #[ORM\Column(name: 'total_consumed', type: Types::DECIMAL, precision: 40, scale: 18)]
    public string $totalConsumed = '0.000000000000000000' {
        get => bcadd($this->totalConsumed, '0', 18);
        set(string $value) => bcadd($value, '0', 18);
    }

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public bool $hasPositiveBalance {
        get => bccomp($this->balance, '0', 18) > 0;
    }

    public string $availableBalance {
        get {
            $avail = bcsub($this->balance, $this->frozenBalance, 18);
            return bccomp($avail, '0', 18) > 0 ? $avail : '0.000000000000000000';
        }
    }

    public function __construct(
        Chat $chat,
        string $currency = 'XTR',
        string $initialBalance = '0.000000000000000000',
        string $initialFrozenBalance = '0.000000000000000000',
    ) {
        $this->chat = $chat;
        $this->currency = strtoupper(trim($currency));
        $this->balance = $initialBalance;
        $this->frozenBalance = $initialFrozenBalance;
        $this->totalConsumed = '0.000000000000000000';
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
