<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tueen\Database\Repository\CacheRepository;

#[ORM\Entity(repositoryClass: CacheRepository::class)]
#[ORM\Table(name: 'cache')]
#[ORM\Index(name: 'idx_cache_expiration_time', columns: ['expiration_time'])]
class CacheItem
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 128)]
    public private(set) string $name;

    /**
     * @var mixed
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public mixed $value;

    #[ORM\Column(name: 'creation_time', type: Types::BIGINT)]
    public private(set) int $creationTime;

    #[ORM\Column(name: 'expiration_time', type: Types::BIGINT, nullable: true)]
    public ?int $expirationTime = null;

    #[ORM\Column(name: 'update_time', type: Types::BIGINT)]
    public int $updateTime;

    public bool $isExpired {
        get => $this->expirationTime !== null && $this->expirationTime <= time();
    }

    public function __construct(
        string $name,
        mixed $value = null,
        ?int $ttlSeconds = null,
    ) {
        $this->name = $name;
        $this->value = $value;
        $now = time();
        $this->creationTime = $now;
        $this->updateTime = $now;
        $this->expirationTime = $ttlSeconds !== null ? $now + $ttlSeconds : null;
    }

    public function touch(?int $newTtl = null): void
    {
        $now = time();
        $this->updateTime = $now;
        if ($newTtl !== null) {
            $this->expirationTime = $now + $newTtl;
        }
    }
}
