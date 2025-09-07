<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Inventory
            ['name' => 'stock.create'],
            ['name' => 'stock.update'],
            ['name' => 'stock.delete'],

            // Sales
            ['name' => 'sale.view'],
            ['name' => 'sale.create'],

            // Transfers
            ['name' => 'stock_transfer.view'],
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

        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'sale.view',
                'sale.create',
                'stock_transfer.view',
                'stock_transfer.create',
                'stock_transfer.receive',
                'report.view_sales',
                'store.update',
            ]);
        }

        $saleperson = Role::where('name', 'saleperson')->first();
        if ($saleperson) {
            $saleperson->givePermissionTo([
                'sale.view',
                'sale.create',
                'stock_transfer.view',
                'report.view_sales',
            ]);
        }
    }
}
