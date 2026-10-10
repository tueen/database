<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tueen\Database\Enum\CouponStatus;
use Tueen\Database\Enum\CouponType;
use Tueen\Database\Repository\CouponRepository;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupons')]
#[ORM\UniqueConstraint(name: 'uk_coupons_code', columns: ['code'])]
#[ORM\Index(name: 'idx_coupons_status_start_end', columns: ['status', 'start_at', 'end_at'])]
#[ORM\Index(name: 'idx_coupons_type_status', columns: ['type', 'status'])]
#[ORM\Index(name: 'idx_coupons_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class Coupon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    #[Assert\NotBlank]
    public string $code {
        set(string $value) => strtoupper(trim($value));
    }

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    public string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: CouponType::class)]
    public CouponType $type = CouponType::Percent;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: CouponStatus::class)]
    public CouponStatus $status = CouponStatus::Active;

    #[ORM\Column(type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $value = '0.000000000000000000';

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    public ?string $currency = null;

    #[ORM\Column(name: 'max_uses', type: Types::INTEGER, nullable: true)]
    public ?int $maxUses = null;

    #[ORM\Column(name: 'max_uses_per_user', type: Types::INTEGER, nullable: true)]
    public ?int $maxUsesPerUser = null;

    #[ORM\Column(name: 'min_order_amount', type: Types::DECIMAL, precision: 36, scale: 18, nullable: true)]
    public ?string $minOrderAmount = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(name: 'allowed_order_types', type: Types::JSON, nullable: true)]
    public ?array $allowedOrderTypes = null;

    /**
     * @var list<int>|null
     */
    #[ORM\Column(name: 'allowed_users', type: Types::JSON, nullable: true)]
    public ?array $allowedUsers = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(name: 'start_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $startAt = null;

    #[ORM\Column(name: 'end_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $endAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, CouponUsage>
     */
    #[ORM\OneToMany(targetEntity: CouponUsage::class, mappedBy: 'coupon', cascade: ['persist', 'remove'])]
    public Collection $usages;

    /**
     * @var Collection<string, CouponMeta>
     */
    #[ORM\OneToMany(targetEntity: CouponMeta::class, mappedBy: 'coupon', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $meta;

    public bool $isValidNow {
        get {
            if ($this->status !== CouponStatus::Active) {
                return false;
            }
            $now = new DateTimeImmutable();
            if ($this->startAt !== null && $this->startAt > $now) {
                return false;
            }
            if ($this->endAt !== null && $this->endAt < $now) {
                return false;
            }
            return true;
        }
    }

    public int $usedCount {
        get => $this->usages->filter(fn(CouponUsage $u) => !$u->reverted)->count();
    }

    public function __construct(
        string $code,
        string $title,
        CouponType $type = CouponType::Percent,
        string $value = '0.000000000000000000',
        CouponStatus $status = CouponStatus::Active,
        ?string $currency = null,
        ?int $maxUses = null,
        ?int $maxUsesPerUser = null,
    ) {
        $this->code = $code;
        $this->title = $title;
        $this->type = $type;
        $this->value = $value;
        $this->status = $status;
        $this->currency = $currency !== null ? strtoupper(trim($currency)) : null;
        $this->maxUses = $maxUses;
        $this->maxUsesPerUser = $maxUsesPerUser;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->usages = new ArrayCollection();
        $this->meta = new ArrayCollection();
    }

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

    public function setMeta(string $key, mixed $value, ?string $group = null): CouponMeta
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

        $metaItem = new CouponMeta($this, $key, $value, $group);
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
