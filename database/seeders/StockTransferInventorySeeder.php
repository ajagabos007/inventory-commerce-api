<?php

namespace Database\Seeders;

use App\Models\StockTransferInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class StockTransferInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stock_transfer_inventories = StockTransferInventory::factory(20)->make()->toArray();

        $start_time = now();
        StockTransferInventory::upsert(
            $stock_transfer_inventories,
            uniqueBy: ['stock_transfer_id', 'inventory_id'],
            update: []
        );

        $end_time = now();
        $stock_transfer_inventories = StockTransferInventory::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])
            ->lazy();

        foreach ($stock_transfer_inventories as $stock_transfer_inventory) {
            if ($stock_transfer_inventory->created_at == $stock_transfer_inventory->updated_at) {
                Event::dispatch('eloquent.created: '.$stock_transfer_inventory::class, $stock_transfer_inventory);
            } else {
                Event::dispatch('eloquent.updated: '.$stock_transfer_inventory::class, $stock_transfer_inventory);
            }
        }
    }
}
