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
use Tueen\Database\Enum\ChatType;
use Tueen\Database\Repository\ChatRepository;

#[ORM\Entity(repositoryClass: ChatRepository::class)]
#[ORM\Table(name: 'chats')]
#[ORM\Index(name: 'idx_chats_type_banned_accessible', columns: ['type', 'banned', 'accessible'])]
#[ORM\Index(name: 'idx_chats_type_active_at', columns: ['type', 'active_at'])]
#[ORM\Index(name: 'idx_chats_created_at', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'uk_chats_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uk_chats_username', columns: ['username'])]
#[ORM\HasLifecycleCallbacks]
class Chat
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BIGINT)]
    public private(set) int $id;

    #[ORM\Column(name: 'public_id', type: Types::STRING, length: 26)]
    public private(set) string $publicId;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChatType::class)]
    #[Assert\NotNull]
    public ChatType $type;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true, nullable: true)]
    #[Assert\Length(max: 64)]
    public ?string $username {
        set(?string $value) {
            if ($value === null) {
                $this->username = null;
                return;
            }
            $clean = strtolower(ltrim(trim($value), '@'));
            $this->username = $clean === '' ? null : $clean;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    public ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 128, nullable: true)]
    #[Assert\Length(max: 128)]
    public ?string $lastName = null;

    #[ORM\Column(name: 'is_bot', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isBot = false;

    #[ORM\Column(name: 'is_premium', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isPremium = false;

    #[ORM\Column(name: 'language_code', type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    public ?string $languageCode = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    public bool $accessible = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $banned = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'active_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $activeAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    /**
     * @var Collection<string, ChatMeta>
     */
    #[ORM\OneToMany(targetEntity: ChatMeta::class, mappedBy: 'chat', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $meta;

    /**
     * @var Collection<string, WalletBalance>
     */
    #[ORM\OneToMany(targetEntity: WalletBalance::class, mappedBy: 'chat', cascade: ['persist'])]
    public Collection $wallets;

    // PHP 8.4 Virtual Property Hooks
    public bool $isPrivate {
        get => $this->type === ChatType::Private;
    }

    public bool $isGroupOrSupergroup {
        get => $this->type === ChatType::Group || $this->type === ChatType::Supergroup;
    }

    public bool $isChannel {
        get => $this->type === ChatType::Channel;
    }

    public string $displayName {
        get {
            if ($this->name !== null && $this->name !== '') {
                return $this->name;
            }
            $fullName = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }
            return $this->username !== null ? '@' . $this->username : (string) $this->id;
        }
    }

    public bool $canReceiveMessages {
        get => $this->accessible && !$this->banned;
    }

    public function __construct(
        int $id,
        ChatType $type = ChatType::Private,
        ?string $username = null,
        ?string $name = null,
        ?string $languageCode = null,
        bool $accessible = true,
        bool $banned = false,
        ?string $publicId = null,
        ?string $firstName = null,
        ?string $lastName = null,
        bool $isBot = false,
        bool $isPremium = false,
    ) {
        $this->id = $id;
        $this->publicId = $publicId ?? Ulid::generate();
        $this->type = $type;
        $this->username = $username;
        $this->name = $name;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->isBot = $isBot;
        $this->isPremium = $isPremium;
        $this->languageCode = $languageCode;
        $this->accessible = $accessible;
        $this->banned = $banned;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->activeAt = $now;
        $this->updatedAt = $now;
        $this->meta = new ArrayCollection();
        $this->wallets = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function touch(): void
    {
        $now = new DateTimeImmutable();
        $this->activeAt = $now;
        $this->updatedAt = $now;
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

    public function setMeta(string $key, mixed $value, ?string $group = null): ChatMeta
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

        $metaItem = new ChatMeta($this, $key, $value, $group);
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
