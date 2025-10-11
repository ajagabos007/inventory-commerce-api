<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum PaymentMode: string
{
    use Enumerable;

    case TEST = 'test';
    case LIVE = 'live';
}
