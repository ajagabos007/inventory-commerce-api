<?php

namespace Database\Seeders;

use App\Models\Sale;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $start_time = now();
        Sale::factory()->count(10)->create();
        $end_time = now();

        $sales = Sale::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])
            ->lazy();

        foreach ($sales as $sale) {
            $sale->updatePricing();
        }
    }
}
