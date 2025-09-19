<?php

namespace Database\Seeders;

use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = ProductVariant::factory(30)->make()->toArray();

        $start_time = now();
        ProductVariant::upsert(
            $products,
            uniqueBy: ['sku'],
            update: []
        );
    }
}
