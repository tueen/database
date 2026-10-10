<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Option;

/**
 * @extends EntityRepository<Option>
 */
class OptionRepository extends EntityRepository
{
    /**
     * @var array<string, mixed>
     */
    private static array $runtimeCache = [];

    public function get(string $name, mixed $default = null): mixed
    {
        if (array_key_exists($name, self::$runtimeCache)) {
            return self::$runtimeCache[$name];
        }

        $option = $this->find($name);
        if ($option === null) {
            return $default;
        }

        self::$runtimeCache[$name] = $option->value;
        return $option->value;
    }

    public function set(string $name, mixed $value, ?string $group = null): Option
    {
        $option = $this->find($name);
        $em = $this->getEntityManager();

        if ($option !== null) {
            $option->value = $value;
            if ($group !== null) {
                $option->group = $group;
            }
        } else {
            $option = new Option($name, $value, $group);
            $em->persist($option);
        }

        $em->flush();
        self::$runtimeCache[$name] = $value;

        return $option;
    }

    public function has(string $name): bool
    {
        if (array_key_exists($name, self::$runtimeCache)) {
            return true;
        }

        return $this->find($name) !== null;
    }

    public function delete(string $name): bool
    {
        unset(self::$runtimeCache[$name]);

        $option = $this->find($name);
        if ($option === null) {
            return false;
        }

        $this->getEntityManager()->remove($option);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getByGroup(string $group): array
    {
        $options = $this->findBy(['group' => $group]);
        $result = [];

        foreach ($options as $opt) {
            $result[$opt->name] = $opt->value;
            self::$runtimeCache[$opt->name] = $opt->value;
        }

        return $result;
    }

    public static function clearRuntimeCache(): void
    {
        self::$runtimeCache = [];
    }
}
