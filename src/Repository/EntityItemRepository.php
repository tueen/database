<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\EntityItem;
use Tueen\Database\Entity\EntityLike;
use Tueen\Database\Entity\EntityMeta;
use Tueen\Database\Entity\EntityRating;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<EntityItem>
 */
class EntityItemRepository extends EntityRepository
{
    public function create(
        Chat|int $chat,
        string $slug,
        string $type = 'entity',
        ?string $title = null,
        ?string $content = null,
        string $status = 'draft',
        ?EntityItem $parent = null,
    ): EntityItem {
        $em = $this->getEntityManager();
        $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
        if ($chatEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
        }

        $entity = new EntityItem(
            chat: $chatEntity,
            slug: $slug,
            type: $type,
            title: $title,
            content: $content,
            status: $status,
            parent: $parent,
        );

        $em->persist($entity);
        $em->flush();

        return $entity;
    }

    public function findBySlug(string $type, string $slug): ?EntityItem
    {
        return $this->findOneBy(['type' => $type, 'slug' => $slug]);
    }

    public function like(EntityItem|int $entity, Chat|int $chat): bool
    {
        $em = $this->getEntityManager();
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);

        if ($item === null || $chatEntity === null) {
            return false;
        }

        $existing = $em->getRepository(EntityLike::class)->findOneBy(['target' => $item, 'chat' => $chatEntity]);
        if ($existing !== null) {
            return false;
        }

        $like = new EntityLike($chatEntity, $item);
        $em->persist($like);
        $em->flush();

        return true;
    }

    public function unlike(EntityItem|int $entity, Chat|int $chat): bool
    {
        $em = $this->getEntityManager();
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);

        if ($item === null || $chatEntity === null) {
            return false;
        }

        $existing = $em->getRepository(EntityLike::class)->findOneBy(['target' => $item, 'chat' => $chatEntity]);
        if ($existing === null) {
            return false;
        }

        $em->remove($existing);
        $em->flush();

        return true;
    }

    public function hasLiked(EntityItem|int $entity, Chat|int $chat): bool
    {
        $itemId = $entity instanceof EntityItem ? $entity->id : $entity;
        $chatId = $chat instanceof Chat ? $chat->id : $chat;

        $existing = $this->getEntityManager()->getRepository(EntityLike::class)->findOneBy(['target' => $itemId, 'chat' => $chatId]);
        return $existing !== null;
    }

    public function getLikesCount(EntityItem|int $entity): int
    {
        $itemId = $entity instanceof EntityItem ? $entity->id : $entity;

        return (int) $this->getEntityManager()->getRepository(EntityLike::class)
            ->createQueryBuilder('l')
            ->select('COUNT(l.chat)')
            ->where('l.target = :itemId')
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function rate(EntityItem|int $entity, Chat|int $chat, int $rating): EntityRating
    {
        $em = $this->getEntityManager();
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);

        if ($item === null) {
            throw RecordNotFoundException::forEntity(EntityItem::class, is_int($entity) ? $entity : $entity->id);
        }
        if ($chatEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
        }

        /** @var EntityRating|null $existing */
        $existing = $em->getRepository(EntityRating::class)->findOneBy(['target' => $item, 'chat' => $chatEntity]);
        if ($existing !== null) {
            $existing->rating = $rating;
            $em->flush();
            return $existing;
        }

        $newRating = new EntityRating($chatEntity, $item, $rating);
        $em->persist($newRating);
        $em->flush();

        return $newRating;
    }

    public function getAverageRating(EntityItem|int $entity): float
    {
        $itemId = $entity instanceof EntityItem ? $entity->id : $entity;

        $avg = $this->getEntityManager()->getRepository(EntityRating::class)
            ->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.target = :itemId')
            ->setParameter('itemId', $itemId)
            ->getQuery()
            ->getSingleScalarResult();

        return $avg !== null ? round((float) $avg, 2) : 0.0;
    }

    public function getMeta(EntityItem|int $entity, string $key, mixed $default = null): mixed
    {
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        return $item?->getMeta($key, $default) ?? $default;
    }

    public function setMeta(EntityItem|int $entity, string $key, mixed $value, ?string $group = null): EntityMeta
    {
        $em = $this->getEntityManager();
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        if ($item === null) {
            throw RecordNotFoundException::forEntity(EntityItem::class, is_int($entity) ? $entity : $entity->id);
        }

        $meta = $item->setMeta($key, $value, $group);
        $em->persist($meta);
        $em->flush();

        return $meta;
    }

    public function deleteMeta(EntityItem|int $entity, string $key): bool
    {
        $em = $this->getEntityManager();
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        if ($item === null) {
            return false;
        }

        $deleted = $item->deleteMeta($key);
        if ($deleted) {
            $em->flush();
        }

        return $deleted;
    }

    public function hasMeta(EntityItem|int $entity, string $key, ?string $group = null): bool
    {
        $item = $entity instanceof EntityItem ? $entity : $this->find($entity);
        return $item?->hasMeta($key, $group) ?? false;
    }
}
