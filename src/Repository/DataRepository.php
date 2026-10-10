<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\DataItem;

/**
 * @extends EntityRepository<DataItem>
 */
class DataRepository extends EntityRepository
{
    public function get(string $group, string $name, Chat|int|null $chat = null, mixed $default = null): mixed
    {
        $item = $this->findItem($group, $name, $chat);
        if ($item === null) {
            return $default;
        }

        $val = $item->value;
        if (is_resource($val)) {
            return stream_get_contents($val);
        }

        return $val ?? $default;
    }

    public function set(string $group, string $name, mixed $value, Chat|int|null $chat = null): DataItem
    {
        $em = $this->getEntityManager();
        $chatEntity = null;
        if ($chat !== null) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
        }

        $item = $this->findItem($group, $name, $chatEntity);
        if ($item !== null) {
            $item->value = $value;
        } else {
            $item = new DataItem($group, $name, $value, $chatEntity);
            $em->persist($item);
        }

        $em->flush();
        return $item;
    }

    public function has(string $group, string $name, Chat|int|null $chat = null): bool
    {
        return $this->findItem($group, $name, $chat) !== null;
    }

    public function delete(string $group, string $name, Chat|int|null $chat = null): bool
    {
        $item = $this->findItem($group, $name, $chat);
        if ($item === null) {
            return false;
        }

        $em = $this->getEntityManager();
        $em->remove($item);
        $em->flush();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getByGroup(string $group, Chat|int|null $chat = null): array
    {
        $criteria = ['group' => $group];
        if ($chat !== null) {
            $chatId = $chat instanceof Chat ? $chat->id : $chat;
            $criteria['chat'] = $chatId;
        } else {
            $criteria['chat'] = null;
        }

        $items = $this->findBy($criteria);
        $result = [];
        foreach ($items as $item) {
            $val = $item->value;
            if (is_resource($val)) {
                $val = stream_get_contents($val);
            }
            $result[$item->name] = $val;
        }

        return $result;
    }

    private function findItem(string $group, string $name, Chat|int|null $chat): ?DataItem
    {
        $criteria = [
            'group' => $group,
            'name' => $name,
        ];

        if ($chat !== null) {
            $chatId = $chat instanceof Chat ? $chat->id : $chat;
            $criteria['chat'] = $chatId;
        } else {
            $criteria['chat'] = null;
        }

        return $this->findOneBy($criteria);
    }
}
