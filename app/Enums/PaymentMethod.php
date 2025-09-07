<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum PaymentMethod: string
{
    use Enumerable;

    case ATM = 'ATM';
    case CHEQUE = 'Cheque';
    case CASH = 'Cash';
    case TRANSFER = 'Transfer';
    case POS = 'POS';
    case OTHER = 'Other';
}
