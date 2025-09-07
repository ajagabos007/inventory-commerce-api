<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => fake()->numberBetween(1, 100),
            'product_id' => Product::InRandomOrder()
                ->where(function ($query) {
                    $query->whereHas('inventories', function ($query) {
                        $query->where('quantity', '<', 0);
                    })->orWhereDoesntHave('inventories');
                })->first()
                         ?? Product::InRandomOrder()->first()
                        ?? Product::factory()->create(),

            'store_id' => Store::InRandomOrder()
                ->where(function ($query) {
                    $query->whereHas('inventories', function ($query) {
                        $query->where('quantity', '<', 0);
                    })->orWhereDoesntHave('inventories');
                })
                ->first()
                        ?? Store::InRandomOrder()->first()
                        ?? Store::factory()->create(),
        ];
    }
}
