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
     * Get all enum names
     *
     * @return array<int,string>
     */
    public static function names(): array
    {
        return array_map(fn ($case) => $case->name, self::cases());
    }

    /**
     * Get enum as associative array (name => value)
     *
     * @return array<string,string>
     */
    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            fn ($carry, $case) => $carry + [$case->name => $case->value],
            []
        );
    }

    /**
     * Get random case
     */
    public static function random(): static
    {
        $cases = self::cases();

        return $cases[array_rand($cases)];
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

    /**
     * Check if value exists
     */
    public static function hasValue(mixed $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to get enum from value
     */
    public static function tryFrom(mixed $value): ?static
    {
        try {
            return parent::tryFrom($value);
        } catch (\TypeError) {
            return null;
        }
    }
}
