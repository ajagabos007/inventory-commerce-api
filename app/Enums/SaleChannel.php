<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum SaleChannel: string
{
    use Enumerable;

    case POS = 'pos';
    case ECOMMERCE = 'ecommerce';

}
