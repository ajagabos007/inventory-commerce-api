<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Product::whereNotNull('id')->delete();
        $products = Product::factory(10)->make()->toArray();

        $start_time = now();
        Product::upsert(
            $products,
            uniqueBy: ['name'],
            update: []
        );

        $end_time = now();

        //        $products = Product::whereBetween('created_at', [
        //            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        //        ])
        //            ->lazy();
        //
        //        foreach ($products as $item) {
        //            if ($item->created_at == $item->updated_at) {
        //                Event::dispatch('eloquent.created: '.$item::class, $item);
        //            } else {
        //                Event::dispatch('eloquent.updated: '.$item::class, $item);
        //            }
        //        }

    }
}
