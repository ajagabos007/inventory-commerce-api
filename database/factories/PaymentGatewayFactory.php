<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $code = fake()->randomElement(['paystack', 'flutterwave']),
            'name' => $name = ucwords($code),
            'description' =>  $name. ' payment gateway',
            'is_default' => false
        ];
    }
}
