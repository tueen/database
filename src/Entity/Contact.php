<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \Tueen\Database\Repository\ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
#[ORM\UniqueConstraint(name: 'uk_contacts_phone_number', columns: ['phone_number'])]
#[ORM\HasLifecycleCallbacks]
class Contact
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    public int $userId {
        get => $this->chat->id;
    }

    #[ORM\Column(name: 'phone_number', type: Types::STRING, length: 32)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    public string $phoneNumber {
        set(string $value) => (string) preg_replace('/\D+/', '', $value);
    }

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $vcard = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public string $fullName {
        get => trim("{$this->firstName} " . ($this->lastName ?? ''));
    }

    public function __construct(
        Chat|int|null $user = null,
        string $phoneNumber = '',
        string $firstName = '',
        ?string $lastName = null,
        ?int $userId = null,
        ?string $vcard = null,
    ) {
        $effectiveUser = $user ?? $userId;
        if ($effectiveUser === null) {
            throw new \InvalidArgumentException('Either $user or $userId must be provided.');
        }

        $this->chat = $effectiveUser instanceof Chat ? $effectiveUser : new Chat(id: $effectiveUser);
        $this->phoneNumber = $phoneNumber;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->vcard = $vcard;
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
