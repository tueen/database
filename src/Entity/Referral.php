<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Enum\ReferralStatus;
use Tueen\Database\Repository\ReferralRepository;

#[ORM\Entity(repositoryClass: ReferralRepository::class)]
#[ORM\Table(name: 'referrals')]
#[ORM\Index(name: 'idx_referrals_referrer_status', columns: ['referrer_id', 'status'])]
#[ORM\Index(name: 'idx_referrals_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class Referral
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'referred_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $referred;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'referrer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $referrer;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ReferralStatus::class)]
    public ReferralStatus $status = ReferralStatus::Pending;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'qualified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $qualifiedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public bool $isQualified {
        get => $this->status === ReferralStatus::Qualified;
    }

    public function __construct(Chat $referred, Chat $referrer)
    {
        if ($referred->id === $referrer->id) {
            throw new \InvalidArgumentException('A chat cannot refer itself.');
        }

        $this->referred = $referred;
        $this->referrer = $referrer;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function qualify(): void
    {
        $this->status = ReferralStatus::Qualified;
        $this->qualifiedAt = new DateTimeImmutable();
    }
}
