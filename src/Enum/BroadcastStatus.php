<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isRunning(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled || $this === self::Failed;
    }
}
