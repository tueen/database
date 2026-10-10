<?php

declare(strict_types=1);

namespace Tueen\Database\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\WalletTransaction;

final class BalanceChangedEvent extends Event
{
    public function __construct(
        public private(set) Chat $chat,
        public private(set) string $currency,
        public private(set) string $amount,
        public private(set) string $newBalance,
        public private(set) ?WalletTransaction $transaction = null,
    ) {}
}
