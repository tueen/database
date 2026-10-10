<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_likes')]
#[ORM\Index(name: 'idx_entity_likes_target_id', columns: ['target_id'])]
class EntityLike
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EntityItem::class)]
    #[ORM\JoinColumn(name: 'target_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) EntityItem $target;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    public function __construct(Chat $chat, EntityItem $target)
    {
        $this->chat = $chat;
        $this->target = $target;
        $this->createdAt = new DateTimeImmutable();
    }
}
