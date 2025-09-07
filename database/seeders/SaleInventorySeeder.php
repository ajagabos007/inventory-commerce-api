<?php

namespace Database\Seeders;

use App\Models\SaleInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class SaleInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sale_inventories = SaleInventory::factory(20)->make()->toArray();

        $start_time = now();

        SaleInventory::upsert(
            $sale_inventories,
            uniqueBy: ['sale_id', 'inventory_id'],
            update: []
        );

        $end_time = now();

        $sale_inventories = SaleInventory::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ]);

        $sale_inventories->clone()->get()->unique('sale_id')->each(function ($sale_inventory) {
            $sale_inventory->sale->updatePricing();
        });

        foreach ($sale_inventories->clone()->lazy() as $sale_inventory) {
            if ($sale_inventory->created_at == $sale_inventory->updated_at) {
                Event::dispatch('eloquent.created: '.$sale_inventory::class, $sale_inventory);
            } else {
                Event::dispatch('eloquent.updated: '.$sale_inventory::class, $sale_inventory);
            }
        }
    }
}
