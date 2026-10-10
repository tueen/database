<?php

declare(strict_types=1);

namespace Tueen\Database\Exception;

class RecordNotFoundException extends DatabaseException
{
    public static function forEntity(string $entityClass, string|int $id): self
    {
        return new self("Record not found for entity [{$entityClass}] with identifier [{$id}].");
    }
}
