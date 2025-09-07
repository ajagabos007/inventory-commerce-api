<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum Material: string
{
    use Enumerable;

    case GOLD = 'Gold';
    case DIAMOND = 'Diamond';
}
