<?php

namespace Database\Factories;

use App\Models\ProductVariant;
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
            'product_variant_id' => ProductVariant::InRandomOrder()
                ->where(function ($query) {
                    $query->whereHas('inventories', function ($query) {
                        $query->where('quantity', '<', 0);
                    })->orWhereDoesntHave('inventories');
                })->first()
                         ?? ProductVariant::InRandomOrder()->first()
                        ?? ProductVariant::factory()->create(),

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
