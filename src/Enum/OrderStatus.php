<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Refunded, self::Failed => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Paid || $this === self::Completed;
    }
}
