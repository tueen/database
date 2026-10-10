<?php

declare(strict_types=1);

namespace Tueen\Database\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tueen\Database\Entity\Order;
use Tueen\Database\Enum\OrderStatus;

final class OrderStatusChangedEvent extends Event
{
    public function __construct(
        public private(set) Order $order,
        public private(set) OrderStatus $oldStatus,
        public private(set) OrderStatus $newStatus,
    ) {}
}
