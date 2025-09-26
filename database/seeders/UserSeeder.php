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
        if(User::query()->exists()){
            return ;
        }

        $admin_email = 'admin@cbm-mall.com';

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

        Role::upsert(
            $roles = [
                ['name' => $admin, 'guard_name' => 'web'],
            ],
            uniqueBy: ['name', 'guard_name'],
            update: (new Role)->getFillable()
        );

        $test_admin = User::where('email', $admin_email)->first();
        $test_admin->assignRole($admin);

    }
}
