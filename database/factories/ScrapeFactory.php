<?php

namespace Database\Factories;

use App\Enums\Type;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scrape>
 */
class ScrapeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(Type::cases())->value;

        return [
            'inventory_id' => $inventory = Inventory::InRandomOrder()->first() ?? Inventory::factory()->create(),
            'quantity' => fake()->numberBetween(1, 100),
            'customer_id' => $type == Type::RETURNED->value ? Customer::InRandomOrder()->first() ?? Customer::factory()->create() : null,
            'staff_id' => Staff::InRandomOrder()
                ->where('store_id', $inventory->store_id)
                ->first()
                        ?? Staff::factory()->create([
                            'store_id' => $inventory->store_id,
                        ]),
            'type' => $type,
            'comment' => match ($type) {
                Type::DAMAGED->value => fake()->randomElement([
                    'Product damaged during handling.',
                    'Product damaged in transit.',
                    'Product found damaged upon inspection.',
                ]),
                Type::RETURNED->value => fake()->randomElement([
                    'Product returned by customer.',
                    'Customer returned item due to dissatisfaction.',
                    'Product not suitable for sale.',
                ]),
            },
        ];
    }
}
