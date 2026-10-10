<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'coupon_usages')]
#[ORM\UniqueConstraint(name: 'uk_coupon_usages_unique_use', columns: ['coupon_id', 'chat_id', 'order_id'])]
#[ORM\Index(name: 'idx_coupon_usages_coupon', columns: ['coupon_id'])]
#[ORM\Index(name: 'idx_coupon_usages_chat', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_coupon_usages_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_coupon_usages_reverted', columns: ['reverted'])]
#[ORM\Index(name: 'idx_coupon_usages_used', columns: ['used_at'])]
class CouponUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Coupon::class, inversedBy: 'usages')]
    #[ORM\JoinColumn(name: 'coupon_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Coupon $coupon;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Order $order;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $reverted = false;

    #[ORM\Column(name: 'discount_amount', type: Types::DECIMAL, precision: 36, scale: 18)]
    public string $discountAmount = '0.000000000000000000';

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(name: 'used_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $usedAt;

    #[ORM\Column(name: 'reverted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?DateTimeImmutable $revertedAt = null;

    public function __construct(
        Coupon $coupon,
        Chat $chat,
        Order $order,
        string $discountAmount = '0.000000000000000000',
    ) {
        $this->coupon = $coupon;
        $this->chat = $chat;
        $this->order = $order;
        $this->discountAmount = $discountAmount;
        $this->usedAt = new DateTimeImmutable();
    }

    public function revert(): void
    {
        $this->reverted = true;
        $this->revertedAt = new DateTimeImmutable();
    }
}
