<?php

declare(strict_types=1);

namespace Tueen\Database\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tueen\Database\Entity\Chat;

final class ChatCreatedEvent extends Event
{
    public function __construct(
        public private(set) Chat $chat,
    ) {}
}
