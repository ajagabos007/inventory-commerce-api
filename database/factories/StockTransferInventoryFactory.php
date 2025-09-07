<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockTransferInventory>
 */
class StockTransferInventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stock_transfer = StockTransfer::whereHas('inventories')->inRandomOrder()->first() ?? StockTransfer::factory()->create();

        return [
            'stock_transfer_id' => $stock_transfer,
            'inventory_id' => $inventory = $stock_transfer->fromStore->inventories()->inRandomOrder()->first() ?? $stock_transfer->fromStore->inventory()->create(),
            'quantity' => fake()->numberBetween(1, max($inventory->quantity, 2)), // Ensure quantity does not exceed available inventory
        ];
    }
}
