<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\TreasuryRepository;

#[ORM\Entity(repositoryClass: TreasuryRepository::class)]
#[ORM\Table(name: 'treasury')]
#[ORM\HasLifecycleCallbacks]
class Treasury
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 10)]
    public private(set) string $currency;

    #[ORM\Column(type: Types::DECIMAL, precision: 40, scale: 18)]
    public string $balance = '0.000000000000000000' {
        get => bcadd($this->balance, '0', 18);
        set(string $value) => bcadd($value, '0', 18);
    }

    #[ORM\Column(name: 'total_commission', type: Types::DECIMAL, precision: 40, scale: 18)]
    public string $totalCommission = '0.000000000000000000' {
        get => bcadd($this->totalCommission, '0', 18);
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

    public function __construct(string $currency = 'XTR')
    {
        $this->currency = strtoupper(trim($currency));
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
