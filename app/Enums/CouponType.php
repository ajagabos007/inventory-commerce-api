<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum CouponType: string
{
    use Enumerable;

    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}
