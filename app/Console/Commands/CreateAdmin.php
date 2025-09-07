<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {

            DB::beginTransaction();

            $admin = 'admin';

            Role::upsert(
                $roles = [['name' => $admin, 'guard_name' => 'web']],
                uniqueBy: ['name', 'guard_name'],
                update: (new Role)->getFillable()
            );

            $input = [];
            $input['email'] = $this->ask('Email');

            if (! is_null($user = User::where('email', $input['email'])->first())) {
                if ($user->hasRole('admin')) {
                    $this->info('Admin created successfully');

                    return;
                }

                $this->warn('There\'s a non admin user(s) registered with the given email');
                $decision = $this->ask('Do you wish to assign admin role to the user? y/n');
                $decision = strtolower($decision);

                if (! \in_array($decision, ['y', 'ye', 'yes'])) {
                    $this->info('Operation terminated successfully');

                    return;
                }
            } else {

                $user = new User;
                $input['first_name'] = $this->ask('First Name');
                $input['last_name'] = $this->ask('Last Name');
                $this->warn('Securely enter your password below');
                $input['password'] = $this->secret('Password ');
                $input['password_confirmation'] = $this->secret('Confirm Password');

                $validator = Validator::make($input, [
                    'first_name' => ['required', 'string', 'max:255'],
                    'last_name' => ['nullable', 'string', 'max:255'],
                    'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                    'password' => [
                        'required',
                        'confirmed',
                        Password::min(8),
                    ],
                ]);

                if ($validator->fails()) {
                    throw new Exception($validator->errors());
                }

                $input['password'] = Hash::make($input['password']);

                $user->forceFill([
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);

                $user->email_verified_at = now();
                $user->save();
            }

            $user->assignRole($admin);

            if (! $user->hasRole($admin)) {
                throw new Exception('Failed! Admin creation failed');
            }

            DB::commit();
            $this->info('Admin created successfully');

        } catch (\Throwable $th) {

            DB::rollBack();
            $this->error($th->getMessage());
        }
    }
}
