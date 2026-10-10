<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
