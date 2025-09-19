<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum InventoryStatus: string
{
    use Enumerable;

    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case DAMAGED = 'damaged';
    case RETURNED = 'returned';
    case TRANSFERRED = 'transferred';

}
