<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            UserSeeder::class,
            StoreSeeder::class,
            //            CategorySeeder::class,
            //            StaffSeeder::class,
            //            AttributeSeeder::class,
            //            ProductSeeder::class,
            //            ProductVariantSeeder::class,
            //            InventorySeeder::class,
            //            DiscountSeeder::class,
            //            SaleSeeder::class,
            //            SaleInventorySeeder::class,
            //            StockTransferSeeder::class,
            //            StockTransferInventorySeeder::class,
            //            ScrapeSeeder::class,
        ]);
    }
}
