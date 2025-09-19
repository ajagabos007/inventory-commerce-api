<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum ConditionStatus: string
{
    use Enumerable;

    case NEW = 'new';
    case RESERVED = 'reserved';
    case REFURBISHED = 'refurbished';
    case USED = 'used';
    case DAMAGED = 'damaged';

}
