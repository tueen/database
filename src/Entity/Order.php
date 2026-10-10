<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;
use Tueen\Database\Enum\OrderStatus;
use Tueen\Database\Enum\TaxMode;
use Tueen\Database\Repository\OrderRepository;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'uk_orders_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_orders_chat_id_created_at', columns: ['chat_id', 'created_at'])]
#[ORM\Index(name: 'idx_orders_type_status_created_at', columns: ['type', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_orders_status_created_at', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'idx_orders_currency', columns: ['currency'])]
#[ORM\Index(name: 'idx_orders_coupon_id', columns: ['coupon_id'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    public ?Chat $chat = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    public string $type;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: OrderStatus::class)]
    public OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    public int $priority = 0;

    #[ORM\Column(type: Types::STRING, length: 10)]
    public string $currency = 'XTR';

    #[ORM\Column(name: 'subtotal_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $subtotalAmount = '0.000000000000000000';

    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $discountAmount = '0.000000000000000000';

    #[ORM\Column(name: 'tax_mode', type: Types::STRING, length: 20, enumType: TaxMode::class)]
    public TaxMode $taxMode = TaxMode::Exclusive;

    #[ORM\Column(name: 'tax_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $taxAmount = '0.000000000000000000';

    #[ORM\Column(name: 'shipping_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $shippingAmount = '0.000000000000000000';

    #[ORM\Column(name: 'payment_method', type: Types::STRING, length: 32, nullable: true)]
    public ?string $paymentMethod = null;

    #[ORM\Column(name: 'payment_currency', type: Types::STRING, length: 10)]
    public string $paymentCurrency = 'XTR';

    #[ORM\Column(name: 'paid_currency', type: Types::STRING, length: 10)]
    public string $paidCurrency = 'XTR';

    #[ORM\Column(name: 'paid_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $paidAmount = '0.000000000000000000';

    #[ORM\Column(name: 'gateway_fee_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $gatewayFeeAmount = '0.000000000000000000';

    #[ORM\Column(name: 'transaction_id', type: Types::STRING, length: 255, nullable: true)]
    public ?string $transactionId = null;

    #[ORM\Column(name: 'reference_id', type: Types::STRING, length: 255, nullable: true)]
    public ?string $referenceId = null;

    #[ORM\Column(name: 'refunded_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $refundedAmount = '0.000000000000000000';

    #[ORM\ManyToOne(targetEntity: Coupon::class)]
    #[ORM\JoinColumn(name: 'coupon_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    public ?Coupon $coupon = null;

    #[ORM\Column(name: 'coupon_code', type: Types::STRING, length: 64, nullable: true)]
    public ?string $couponCode = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'coupon_snapshot', type: Types::JSON, nullable: true)]
    public ?array $couponSnapshot = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $payload = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(name: 'admin_note', type: Types::TEXT, nullable: true)]
    public ?string $adminNote = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $paidAt = null;

    #[ORM\Column(name: 'refunded_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $refundedAt = null;

    #[ORM\Column(name: 'rejected_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @var Collection<string, OrderMeta>
     */
    #[ORM\OneToMany(targetEntity: OrderMeta::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $meta;

    // PHP 8.4 Virtual Property Hooks
    public bool $isPaid {
        get => $this->status === OrderStatus::Paid || $this->status === OrderStatus::Completed;
    }

    public string $totalAmount {
        get {
            $base = bcadd($this->subtotalAmount, $this->shippingAmount, 18);
            if ($this->taxMode === TaxMode::Exclusive) {
                $base = bcadd($base, $this->taxAmount, 18);
            }
            $final = bcsub($base, $this->discountAmount, 18);
            return bccomp($final, '0', 18) > 0 ? $final : '0.000000000000000000';
        }
    }

    public function __construct(
        string $type,
        ?Chat $chat = null,
        string $currency = 'XTR',
        string $subtotalAmount = '0.000000000000000000',
        OrderStatus $status = OrderStatus::Pending,
        ?string $publicId = null,
    ) {
        $this->type = $type;
        $this->chat = $chat;
        $this->currency = strtoupper(trim($currency));
        $this->paymentCurrency = $this->currency;
        $this->paidCurrency = $this->currency;
        $this->subtotalAmount = $subtotalAmount;
        $this->status = $status;
        $this->publicId = $publicId ?? Ulid::generate();
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->meta = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsPaid(?string $transactionId = null, ?string $referenceId = null, ?string $paidAmount = null): void
    {
        $this->status = OrderStatus::Paid;
        $this->paidAt = new DateTimeImmutable();
        $this->paidAmount = $paidAmount ?? $this->totalAmount;
        if ($transactionId !== null) {
            $this->transactionId = $transactionId;
        }
        if ($referenceId !== null) {
            $this->referenceId = $referenceId;
        }
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

    public function setMeta(string $key, mixed $value, ?string $group = null): OrderMeta
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

        $metaItem = new OrderMeta($this, $key, $value, $group);
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
