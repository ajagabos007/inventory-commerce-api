<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [

            ['name' => 'attribute.viewAny'],
            ['name' => 'attribute.view'],
            ['name' => 'attribute.create'],
            ['name' => 'attribute.update'],
            ['name' => 'attribute.delete'],

            ['name' => 'attribute-value.viewAny'],
            ['name' => 'attribute-value.view'],
            ['name' => 'attribute-value.create'],
            ['name' => 'attribute-value.update'],
            ['name' => 'attribute-value.delete'],

            ['name' => 'product.viewAny'],
            ['name' => 'product.view'],
            ['name' => 'product.create'],
            ['name' => 'product.update'],
            ['name' => 'product.delete'],

            ['name' => 'product-variant.viewAny'],
            ['name' => 'product-variant.view'],
            ['name' => 'product-variant.create'],
            ['name' => 'product-variant.update'],
            ['name' => 'product-variant.delete'],

            // Inventory
            ['name' => 'stock.create'],
            ['name' => 'stock.update'],
            ['name' => 'stock.delete'],

            // Sales
            ['name' => 'sale.view'],
            ['name' => 'sale.create'],

            // Transfers
            ['name' => 'attribute.view'],
            ['name' => 'stock_transfer.create'],
            ['name' => 'stock_transfer.receive'],

            // Pricing
            ['name' => 'daily_price.create'],
            ['name' => 'daily_price.update'],

            // Reports
            ['name' => 'report.view_sales'],

            // Tagging & Category
            ['name' => 'attribute.manage'],

            // Store-level
            ['name' => 'store.switch'],
            ['name' => 'store.create'],
            ['name' => 'store.update'],
            ['name' => 'store.delete'],
        ];

        // Add guard_name to each permission
        foreach ($permissions as &$permission) {
            $permission['guard_name'] = 'web';
        }

        Permission::upsert(
            $permissions,
            uniqueBy: ['name'],
            update: []
        );
    }
}
