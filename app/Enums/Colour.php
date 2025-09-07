<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum Colour: string
{
    use Enumerable;

    case MIXED = 'Mixed';
    case ROSE = 'Rose';
    case WHITE = 'White';
    case YELLOW = 'Yellow';
}
