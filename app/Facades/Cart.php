<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void add(string $id, string $name, float $price, int $quantity = 1, array $options = [])
 * @method static void remove(string $id)
 * @method static void increase(string $id, int $quantity = 1)
 * @method static void decrease(string $id, int $quantity = 1)
 * @method static void clear()
 * @method static array all()
 * @method static int count()
 * @method static float total()
 */
class Cart extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cart';
    }
}
