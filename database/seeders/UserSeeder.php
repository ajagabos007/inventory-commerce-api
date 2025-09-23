<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin_email = 'admin@cbm-mall.com';
        $salesperson_email = 'salesperson@cbm-mall.com';
        $manager_email = 'manager@cbm-mall.com';

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Alice',
            'last_name' => 'User',
            'email' => 'user@cmb-mall.com',
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Staff',
            'last_name' => 'Support',
            'email' => 'support@cbm-mall.com',
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Staff',
            'last_name' => 'Salesperson',
            'email' => $salesperson_email,
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Staff',
            'last_name' => 'Manager',
            'email' => $manager_email,
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $admin_email = 'admin@cmb-mall.com';
        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Admin',
            'last_name' => 'CBM-Mall',
            'email' => $admin_email,
        ])->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        User::upsert(
            $data = $users,
            uniqueBy: ['phone_number', 'email'],
            update: []
        );

        $admin = 'admin';
        $salesperson = 'salesperson';
        $manager = 'manager';

        Role::upsert(
            $roles = [
                ['name' => $admin, 'guard_name' => 'web'],
                ['name' => $salesperson, 'guard_name' => 'web'],
                ['name' => $manager, 'guard_name' => 'web'],
            ],
            uniqueBy: ['name', 'guard_name'],
            update: (new Role)->getFillable()
        );

        $test_admin = User::where('email', $admin_email)->first();
        $test_admin->assignRole($admin);

        $test_salesperson = User::where('email', $salesperson_email)->first();
        $test_salesperson->assignRole($salesperson);

        $test_manager = User::where('email', $manager_email)->first();
        $test_manager->assignRole($manager);

    }
}
