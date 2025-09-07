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
        $admin_email = 'admin@example.test';
        $saleperson_email = 'saleperson@example.test';
        $manager_email = 'manager@example.test';

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Alice',
            'last_name' => 'Test',
            'email' => 'alice@example.test',
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Staff',
            'last_name' => 'Test',
            'email' => 'staff@example.test',
        ])
            ->makeVisible(['password'])
            ->makeHidden(['profile_photo_url'])
            ->toArray();

        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Staff',
            'last_name' => 'Saleperson',
            'email' => $saleperson_email,
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

        $admin_email = 'admin@example.test';
        $users[] = User::factory()->unverified()->make([
            'first_name' => 'Admin',
            'last_name' => 'Test',
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
        $saleperson = 'saleperson';
        $manager = 'manager';

        Role::upsert(
            $roles = [
                ['name' => $admin, 'guard_name' => 'web'],
                ['name' => $saleperson, 'guard_name' => 'web'],
                ['name' => $manager, 'guard_name' => 'web'],
            ],
            uniqueBy: ['name', 'guard_name'],
            update: (new Role)->getFillable()
        );

        $test_admin = User::where('email', $admin_email)->first();
        $test_admin->assignRole($admin);

        $test_saleperson = User::where('email', $saleperson_email)->first();
        $test_saleperson->assignRole($saleperson);

        $test_manager = User::where('email', $manager_email)->first();
        $test_manager->assignRole($manager);

    }
}
