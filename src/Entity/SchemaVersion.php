<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'schema_versions')]
#[ORM\UniqueConstraint(name: 'uk_schema_versions_name', columns: ['name'])]
class SchemaVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public private(set) ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    public private(set) string $name;

    #[ORM\Column(type: Types::INTEGER)]
    public int $version;

    #[ORM\Column(name: 'applied_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $appliedAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $description = null;

    public function __construct(string $name, int $version, ?string $description = null)
    {
        $this->name = $name;
        $this->version = $version;
        $this->description = $description;
        $this->appliedAt = new DateTimeImmutable();
    }
}
