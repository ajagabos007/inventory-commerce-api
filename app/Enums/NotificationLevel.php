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

    /**
     * Get user-friendly label
     */
    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Info',
            self::SUCCESS => 'Success',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
        };
    }
}
