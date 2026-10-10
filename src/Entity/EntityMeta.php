<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_meta')]
#[ORM\Index(name: 'idx_entity_meta_group_entity_id', columns: ['group_name', 'entity_id'])]
#[ORM\HasLifecycleCallbacks]
class EntityMeta
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: EntityItem::class, inversedBy: 'meta')]
    #[ORM\JoinColumn(name: 'entity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) EntityItem $entity;

    #[ORM\Id]
    #[ORM\Column(name: 'meta_key', type: Types::STRING, length: 128)]
    public private(set) string $metaKey;

    /**
     * @var mixed
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public mixed $value;

    #[ORM\Column(name: 'group_name', type: Types::STRING, length: 64, nullable: true)]
    public ?string $group = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct(
        EntityItem $entity,
        string $metaKey,
        mixed $value = null,
        ?string $group = null,
    ) {
        $this->entity = $entity;
        $this->metaKey = $metaKey;
        $this->value = $value;
        $this->group = $group;
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
