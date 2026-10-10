<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'entity_ratings')]
#[ORM\Index(name: 'idx_entity_ratings_target_id', columns: ['target_id'])]
#[ORM\HasLifecycleCallbacks]
class EntityRating
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EntityItem::class)]
    #[ORM\JoinColumn(name: 'target_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) EntityItem $target;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 100)]
    public int $rating;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct(Chat $chat, EntityItem $target, int $rating)
    {
        $this->chat = $chat;
        $this->target = $target;
        $this->rating = $rating;
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
