<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $count = Store::count() + ProductVariant::count();
        $inventories = Inventory::factory(max($count, 10))->make()->toArray();

        $start_time = now();

        Inventory::upsert(
            $inventories,
            uniqueBy: ['store_id', 'product_variant_id'],
            update: []
        );

        $end_time = now();

        $inventories = Inventory::whereBetween('created_at', [
            $start_time->toDateTimeString(),
            $end_time->toDateTimeString(),
        ])
            ->lazy();

        foreach ($inventories as $inventory) {
            if ($inventory->created_at == $inventory->updated_at) {
                Event::dispatch('eloquent.created: '.$inventory::class, $inventory);
            } else {
                Event::dispatch('eloquent.updated: '.$inventory::class, $inventory);
            }
        }

    }
}
