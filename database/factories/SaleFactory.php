<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\Sale;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => Sale::generateInvoiceNumber(),
            'cashier_staff_id' => Staff::InRandomOrder()->first() ?? Staff::factory()->create(),
            'discount_id' => fake()->boolean() ? (Discount::InRandomOrder()->first() ?? Discount::create()) : null,
            'customer_user_id' => $customer = fake()->boolean() ? (User::InRandomOrder()->first()) : null,
            'customer_name' => is_null($customer) ? fake()->name() : $customer->name,
            'customer_email' => is_null($customer) ? fake()->optional()->safeEmail() : $customer->email,
            'customer_phone_number' => is_null($customer) ? fake()->optional()->phoneNumber() : $customer->phone_number,
            'tax' => fake()->randomFloat(2, 0, 100),
        ];
    }
}
