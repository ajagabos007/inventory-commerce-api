<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum TimePeriod: string
{
    use Enumerable;

    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';
}
