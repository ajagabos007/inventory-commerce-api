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
            ['name' => 'inventory.viewAny'],
            ['name' => 'inventory.view'],
            ['name' => 'inventory.create'],
            ['name' => 'inventory.update'],
            ['name' => 'inventory.delete'],

            // Sales
            ['name' => 'sale.viewAny'],
            ['name' => 'sale.view'],
            ['name' => 'sale.create'],
            ['name' => 'sale.update'],
            ['name' => 'sale.delete'],

            // Transfers
            ['name' => 'stock_transfer.viewAny'],
            ['name' => 'stock_transfer.view'],
            ['name' => 'stock_transfer.create'],
            ['name' => 'stock_transfer.update'],
            ['name' => 'stock_transfer.delete'],
            ['name' => 'stock_transfer.receive'],

            // Reports
            ['name' => 'report.view_sales'],

            // Store-level
            ['name' => 'store.viewAny'],
            ['name' => 'store.view'],
            ['name' => 'store.switch'],
            ['name' => 'store.create'],
            ['name' => 'store.update'],
            ['name' => 'store.delete'],

            // Customer-level
            ['name' => 'customer.viewAny'],
            ['name' => 'customer.view'],
            ['name' => 'customer.create'],
            ['name' => 'customer.update'],
            ['name' => 'customer.delete'],
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
