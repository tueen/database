<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Tueen\Database\Repository\DataRepository::class)]
#[ORM\Table(name: 'data')]
#[ORM\UniqueConstraint(name: 'uk_data', columns: ['chat_id', 'group_name', 'name'])]
#[ORM\Index(name: 'idx_data_chat_group', columns: ['chat_id', 'group_name'])]
#[ORM\HasLifecycleCallbacks]
class DataItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) ?int $id = null;

    #[ORM\Column(name: 'group_name', type: Types::STRING, length: 64)]
    public string $group;

    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $name;

    #[ORM\ManyToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?Chat $chat = null;

    /**
     * @var string|resource|null
     */
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    public mixed $value = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct(
        string $group,
        string $name,
        mixed $value = null,
        ?Chat $chat = null,
    ) {
        $this->group = $group;
        $this->name = $name;
        $this->value = $value;
        $this->chat = $chat;
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
