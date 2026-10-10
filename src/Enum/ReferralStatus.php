<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
}
