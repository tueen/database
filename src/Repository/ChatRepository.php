<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\ChatMeta;
use Tueen\Database\Enum\ChatType;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Chat>
 */
class ChatRepository extends EntityRepository
{
    /**
     * Finds an existing Chat or automatically registers a new one.
     */
    public function findOrCreate(
        int $chatId,
        ?string $username = null,
        ?string $name = null,
        ChatType $type = ChatType::Private,
        ?string $languageCode = null,
        ?string $firstName = null,
        ?string $lastName = null,
        bool $isBot = false,
        bool $isPremium = false,
    ): Chat {
        $cleanUsername = null;
        if ($username !== null) {
            $clean = strtolower(ltrim(trim($username), '@'));
            if ($clean !== '') {
                $cleanUsername = $clean;
            }
        }

        // If a username is provided, release/null it from any previous chat owner
        if ($cleanUsername !== null) {
            $previousOwner = $this->findOneBy(['username' => $cleanUsername]);
            if ($previousOwner !== null && $previousOwner->id !== $chatId) {
                $previousOwner->username = null;
                $this->getEntityManager()->flush();
            }
        }

        $chat = $this->find($chatId);

        if ($chat !== null) {
            if ($username !== null) {
                $chat->username = $cleanUsername;
            }
            if ($name !== null) {
                $chat->name = $name;
            }
            if ($firstName !== null) {
                $chat->firstName = $firstName;
            }
            if ($lastName !== null) {
                $chat->lastName = $lastName;
            }
            if ($languageCode !== null) {
                $chat->languageCode = $languageCode;
            }
            if ($isBot) {
                $chat->isBot = true;
            }
            if ($isPremium) {
                $chat->isPremium = true;
            }

            $chat->touch();
            $this->getEntityManager()->flush();

            return $chat;
        }

        $chat = new Chat(
            id: $chatId,
            type: $type,
            username: $cleanUsername,
            name: $name,
            languageCode: $languageCode,
            firstName: $firstName,
            lastName: $lastName,
            isBot: $isBot,
            isPremium: $isPremium,
        );

        $this->getEntityManager()->persist($chat);
        $this->getEntityManager()->flush();

        return $chat;
    }

    public function findByUsername(string $username): ?Chat
    {
        $cleanUsername = strtolower(ltrim(trim($username), '@'));
        if ($cleanUsername === '') {
            return null;
        }

        return $this->findOneBy(['username' => $cleanUsername]);
    }

    public function getMeta(Chat|int $chat, string $key, mixed $default = null): mixed
    {
        $chatEntity = $chat instanceof Chat ? $chat : $this->find($chat);
        return $chatEntity?->getMeta($key, $default) ?? $default;
    }

    public function setMeta(Chat|int $chat, string $key, mixed $value, ?string $group = null): ChatMeta
    {
        $em = $this->getEntityManager();
        $chatEntity = $chat instanceof Chat ? $chat : $this->find($chat);
        if ($chatEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
        }

        $meta = $chatEntity->setMeta($key, $value, $group);
        $em->persist($meta);
        $em->flush();

        return $meta;
    }

    public function deleteMeta(Chat|int $chat, string $key): bool
    {
        $em = $this->getEntityManager();
        $chatEntity = $chat instanceof Chat ? $chat : $this->find($chat);
        if ($chatEntity === null) {
            return false;
        }

        $deleted = $chatEntity->deleteMeta($key);
        if ($deleted) {
            $em->flush();
        }

        return $deleted;
    }

    public function hasMeta(Chat|int $chat, string $key, ?string $group = null): bool
    {
        $chatEntity = $chat instanceof Chat ? $chat : $this->find($chat);
        return $chatEntity?->hasMeta($key, $group) ?? false;
    }

    /**
     * @return list<Chat>
     */
    public function findActive(int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.accessible = true')
            ->andWhere('c.banned = false')
            ->orderBy('c.activeAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function ban(int $chatId): void
    {
        $chat = $this->find($chatId);
        if ($chat !== null) {
            $chat->banned = true;
            $this->getEntityManager()->flush();
        }
    }

    public function unban(int $chatId): void
    {
        $chat = $this->find($chatId);
        if ($chat !== null) {
            $chat->banned = false;
            $this->getEntityManager()->flush();
        }
    }

    public function setAccessible(int $chatId, bool $accessible): void
    {
        $chat = $this->find($chatId);
        if ($chat !== null) {
            $chat->accessible = $accessible;
            $this->getEntityManager()->flush();
        }
    }
}
