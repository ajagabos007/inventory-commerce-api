<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum InventoryStatus: string
{
    use Enumerable;

    case AVAILABLE = 'available';
    case UNAVAILABLE  = 'unavailable';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case DAMAGED = 'damaged';
    case RETURNED = 'returned';
    case TRANSFERRED = 'transferred';
    case OUT_OF_STOCK = 'out_of_stock';

}
