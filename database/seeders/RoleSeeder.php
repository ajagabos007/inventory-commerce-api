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
        if(Role::query()->exists()){
            return;
        }

        $roles = [
            [
                'name' => 'admin',
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
