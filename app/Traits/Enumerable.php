<?php

namespace App\Traits;

trait Enumerable
{
    /**
     * Get all the values of the enum
     *
     * @return array<int, string>
     */
    public static function values(): array
    {

        return enum_exists(self::class) ? array_column(self::cases(), 'value') : [];
    }

    /**
     * Get all the values of the enum in upper case
     *
     * @return array<int, string>
     */
    public static function valuesToUpperCase(): array
    {
        return array_map(function ($value) {
            return strtoupper($value);
        }, self::values());
    }

    /**
     * Get all the values of the enum in lower case
     *
     * @return array<int, string>
     */
    public static function valuesToLowerCase(): array
    {
        return array_map(function ($value) {
            return strtolower($value);
        }, self::values());
    }

    /**
     * Get all name value pair of the enum
     *
     * @return array<string, string>
     */
    public static function forSelect(): array
    {
        return enum_exists(self::class) ? array_column(self::cases(), 'value', 'name') : [];
    }
}
