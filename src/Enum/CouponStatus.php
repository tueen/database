<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum CouponStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
}
