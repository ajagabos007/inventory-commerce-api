<?php

namespace App\Enums;

use App\Traits\Enumerable;

enum AttributeType: string
{
    use Enumerable;
    case TEXT = 'text';
    case NUMBER = 'number';
    case COLOR = 'color';
    case DATE = 'date';

    /**
     * @return string[]
     */
    public static function rules($type): array
    {
        return match (strtolower($type)) {
            self::Number->value => ['numeric'],
            self::COLOR->value => ['hex_color'],
            self::DATE->value => ['date'],
            default => ['string', 'max:191'],
        };
    }

    /**
     * Get user-friendly label
     */
    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::NUMBER => 'Number',
            self::COLOR => 'Color',
            self::DATE => 'Date',
        };
    }
}
