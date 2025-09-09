<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum ScrapeType: string
{
    use Enumerable;

    case RETURNED = 'returned';
    case DAMAGED = 'damaged';

}
