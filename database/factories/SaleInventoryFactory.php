<?php

namespace Database\Factories;

use App\Enums\Material;
use App\Models\DailyGoldPrice;
use App\Models\Inventory;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleInventory>
 */
class SaleInventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inventory = Inventory::InRandomOrder()->first() ?? Inventory::factory()->create();
        $daily_gold_price = null;
        $quantity = fake()->numberBetween(1, 15);
        $price_per_gram = $inventory->item->price;

        if ($inventory->item->material == Material::GOLD->value) {
            $daily_gold_price = DailyGoldPrice::period('today')->first();
            $daily_gold_price = $daily_gold_price ?? DailyGoldPrice::first();
            if ($daily_gold_price) {

            }
            $price_per_gram = $daily_gold_price ? $daily_gold_price->price_per_gram : fake()->randomFloat(2, 1000, 100000);
        }

        $total_price = $price_per_gram * $quantity;
        if ($inventory->item->weight > 0) {
            $total_price *= $inventory->item->weight;
        }

        return [
            'inventory_id' => $inventory,
            'sale_id' => $sale = Sale::InRandomOrder()->first() ?? Sale::factory()->create(),
            'quantity' => $quantity,
            'weight' => $inventory->item->weight,
            'price_per_gram' => $price_per_gram,
            'total_price' => $total_price,
            'daily_gold_price_id' => $daily_gold_price,
        ];
    }
}
