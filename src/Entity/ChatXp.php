<?php

declare(strict_types=1);

namespace Tueen\Database\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Tueen\Database\Repository\ChatXpRepository::class)]
#[ORM\Table(name: 'chat_xp')]
#[ORM\Index(name: 'idx_chat_xp_ranking', columns: ['xp', 'chat_id'])]
#[ORM\HasLifecycleCallbacks]
class ChatXp
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Chat::class)]
    #[ORM\JoinColumn(name: 'chat_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public private(set) Chat $chat;

    #[ORM\Column(type: Types::BIGINT)]
    public int $xp = 0;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public private(set) DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $updatedAt;

    public function __construct(Chat $chat, int $initialXp = 0)
    {
        $this->chat = $chat;
        $this->xp = $initialXp;
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
