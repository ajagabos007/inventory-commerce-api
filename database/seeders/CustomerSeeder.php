<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(Customer::query()->exists()){
            return ;
        }

        Customer::factory()->create([
            'name' => 'walk-in-customer',
            'email' => 'customer@email.domain',
            'phone_number' => null,
            'country' => null,
            'city' => null,
            'address' => null
        ]);
    }
}
