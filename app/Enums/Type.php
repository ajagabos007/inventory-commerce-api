<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum Type: string
{
    use Enumerable;

    case RETURNED = 'returned';
    case DAMAGED = 'damaged';
}
