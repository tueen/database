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

#[ORM\Entity(repositoryClass: \Tueen\Database\Repository\EntityItemRepository::class)]
#[ORM\Table(name: 'entities')]
#[ORM\UniqueConstraint(name: 'uk_entities_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uk_entities_type_slug', columns: ['type', 'slug'])]
#[ORM\Index(name: 'idx_entities_chat', columns: ['chat_id'])]
#[ORM\Index(name: 'idx_entities_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_entities_type_status_created', columns: ['type', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_entities_chat_type_status', columns: ['chat_id', 'type', 'status'])]
#[ORM\Index(name: 'idx_entities_priority_created', columns: ['priority', 'created_at'])]
#[ORM\Index(name: 'idx_entities_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class EntityItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    public string $type = 'entity';

    #[ORM\Column(type: Types::STRING, length: 120)]
    #[Assert\NotBlank]
    public string $slug;

    #[ORM\Column(type: Types::STRING, length: 20)]
    public string $status = 'draft';

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?self $parent = null;

    /**
     * @var Collection<int, EntityItem>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    public Collection $children;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    public int $priority = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $content = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $extra = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @var Collection<string, EntityMeta>
     */
    #[ORM\OneToMany(targetEntity: EntityMeta::class, mappedBy: 'entity', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $meta;

    public function __construct(
        Chat $chat,
        string $slug,
        string $type = 'entity',
        ?string $title = null,
        ?string $content = null,
        string $status = 'draft',
        ?self $parent = null,
        ?string $publicId = null,
    ) {
        $this->chat = $chat;
        $this->slug = $slug;
        $this->type = $type;
        $this->title = $title;
        $this->content = $content;
        $this->status = $status;
        $this->parent = $parent;
        $this->publicId = $publicId ?? Ulid::generate();
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->children = new ArrayCollection();
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

    public function setMeta(string $key, mixed $value, ?string $group = null): EntityMeta
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

        $metaItem = new EntityMeta($this, $key, $value, $group);
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
