<?php

declare(strict_types=1);

namespace Tueen\Database\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class MigrationExecutedEvent extends Event
{
    public function __construct(
        public private(set) string $version,
        public private(set) string $direction, // 'up' or 'down'
        public private(set) int $executionTimeMs = 0,
    ) {}
}
