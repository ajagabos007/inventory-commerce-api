<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Enums\Permission as PermissionEnum;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];

        foreach (PermissionEnum::values() as $permission) {
            $permissions[] = [
                'name'       => $permission,
                'guard_name' => 'web',
            ];
        }

        Permission::upsert(
            $permissions,
            uniqueBy: ['name'],
            update: []
        );

        return ;
        Permission::whereNotIn('name', data_get($permissions, '*.name'))->delete();

        // ----------------------------------------
        // Create Roles
        // ----------------------------------------
        $adminRole   = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $salesRepRole = Role::firstOrCreate(['name' => 'sales rep.', 'guard_name' => 'web']);

        // ----------------------------------------
        // Admin → ALL permissions
        // ----------------------------------------
        $adminRole->syncPermissions(Permission::all());

        // ----------------------------------------
        // Manager → Has access to operations but not roles/users/security
        // ----------------------------------------
        $managerPermissions = Permission::where(function ($query) {
            $query->where('name', 'like', 'products.%')
                ->orWhere('name', 'like', 'orders.%')
                ->orWhere('name', 'like', 'stock_transfers.%')
                ->orWhere('name', 'like', 'inventories.%')
                ->orWhere('name', 'like', 'customers.%')
                ->orWhere('name', 'like', 'suppliers.%')
                ->orWhere('name', 'like', 'staff.%')
                ->orWhere('name', 'like', 'pos.%');
        })->get();

        $managerRole->syncPermissions($managerPermissions);


        // ----------------------------------------
        // Sales Rep → Only POS, customers, and basic inventory
        // ----------------------------------------
        $salesRepPermissions = Permission::where(function ($query) {
            $query->whereIn('name', [
                // Product (read-only)
                'products.viewAny',
                'products.view',

                // POS
                'pos.checkout',

                // Customers
                'customers.viewAny',
                'customers.view',
                'customers.create',
                'customers.update',

                // Inventory (read-only)
                'inventories.viewAny',
                'inventories.view',

                // Orders (read-only)
                'orders.viewAny',
                'orders.view',

                // Stock transfers (view-only)
                'stock_transfers.viewAny',
                'stock_transfers.view',
            ]);
        })->get();

        $salesRepRole->syncPermissions($salesRepPermissions);
    }
}
