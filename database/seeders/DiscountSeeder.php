<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $discounts = Discount::factory(10)->make()->toArray();

        Discount::upsert(
            $discounts,
            uniqueBy: ['code'],
            update: null
        );
    }
}
