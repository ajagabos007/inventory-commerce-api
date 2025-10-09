<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $code = fake()->randomElement(['NGN', 'USD', 'GBP']),
            'code' => $code ,
            'symbol' => $code,
            'is_default' => fake()->boolean,
            'disabled_at' => $disabled_at =  fake()->boolean ? fake()->dateTime: null,
            'disabled_reason' => blank($disabled_at) ? null : fake()->realText(),
        ];
    }
}
