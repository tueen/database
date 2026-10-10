<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled => true,
            default => false,
        };
    }
}
