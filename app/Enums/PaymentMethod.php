<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum PaymentMethod: string
{
    use Enumerable;

    case POS = 'POS';
    case TRANSFER = 'Transfer';
    case CHEQUE = 'Cheque';

}
