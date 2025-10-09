<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(Currency::exists()) {
            return;
        }

        $naira = Currency::factory()->create([
            'name' => 'Naira',
            'code' => 'NGN',
            'symbol' => '₦',
            'exchange_rate' => 1472.15,
            'is_default' => true,
            'disabled_at' => null,
            'disabled_reason' => null,
        ]);
    }
}
