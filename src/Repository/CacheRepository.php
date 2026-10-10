<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Psr\SimpleCache\CacheInterface;
use Tueen\Database\Entity\CacheItem;

/**
 * @extends EntityRepository<CacheItem>
 */
class CacheRepository extends EntityRepository implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->find($key);
        if ($item === null) {
            return $default;
        }

        if ($item->isExpired) {
            $this->delete($key);
            return $default;
        }

        return $item->value;
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $ttlSeconds = $this->resolveTtlSeconds($ttl);
        $em = $this->getEntityManager();
        $item = $this->find($key);

        if ($item !== null) {
            $item->value = $value;
            $item->touch($ttlSeconds);
        } else {
            $item = new CacheItem($key, $value, $ttlSeconds);
            $em->persist($item);
        }

        $em->flush();
        return true;
    }

    public function delete(string $key): bool
    {
        $item = $this->find($key);
        if ($item === null) {
            return false;
        }

        $this->getEntityManager()->remove($item);
        $this->getEntityManager()->flush();

        return true;
    }

    public function clear(): bool
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->getQuery()
            ->execute();

        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $item = $this->find($key);
        if ($item === null) {
            return false;
        }

        if ($item->isExpired) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Purges all expired cache records.
     */
    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->where('c.expirationTime IS NOT NULL')
            ->andWhere('c.expirationTime <= :now')
            ->setParameter('now', time())
            ->getQuery()
            ->execute();
    }

    private function resolveTtlSeconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        $now = new DateTimeImmutable();
        $future = $now->add($ttl);

        return $future->getTimestamp() - $now->getTimestamp();
    }
}
