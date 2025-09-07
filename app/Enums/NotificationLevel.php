<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum NotificationLevel: string
{
    use Enumerable;

    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
