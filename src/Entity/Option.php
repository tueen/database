<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\OptionRepository;

#[ORM\Entity(repositoryClass: OptionRepository::class)]
#[ORM\Table(name: 'options')]
#[ORM\Index(name: 'idx_options_group', columns: ['group_name'])]
#[ORM\HasLifecycleCallbacks]
class Option
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 128)]
    public private(set) string $name;

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

    public function __construct(string $name, mixed $value = null, ?string $group = null)
    {
        $this->name = $name;
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
