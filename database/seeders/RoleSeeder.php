<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'web',
            ],
            [
                'name' => 'saleperson',
                'guard_name' => 'web',
            ],
            [
                'name' => 'manager',
                'guard_name' => 'web',
            ],

        ];

        Role::upsert(
            $roles,
            uniqueBy: ['name'],
            update: []
        );

    }
}
