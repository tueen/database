<?php

declare(strict_types=1);

namespace Tueen\Database\Enum;

enum TaxMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';
    case Exempt = 'exempt';
    case ReverseCharge = 'reverse_charge';
}
