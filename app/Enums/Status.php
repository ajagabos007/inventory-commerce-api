<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum Status: string
{
    use Enumerable;

    case NEW = 'new';
    case DISPATCHED = 'dispatched';
    case RECEIVED = 'received';
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
