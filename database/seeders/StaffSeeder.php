<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::whereDoesntHave('staff')
            ->whereIn('email', [
                'admin@example.test',
                'staff@example.test',
                'salesperson@example.test',
                'manager@example.test',
            ])
            ->get();
        $staff = collect();
        foreach ($user as $_user) {
            $staff->push(Staff::factory()->make([
                'user_id' => $_user->id,
            ])->toArray());
        }
        if ($staff->isEmpty()) {
            return;
        }

        $start_time = now();
        Staff::upsert(
            $staff->toArray(),
            uniqueBy: ['staff_no', 'user_id'],
            update: ['staff_no']
        );

        $end_time = now();

        $staff = Staff::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])->lazy();

        foreach ($staff as $_staff) {
            if ($_staff->created_at == $_staff->updated_at) {
                Event::dispatch('eloquent.created: '.$_staff::class, $_staff);
            } else {
                Event::dispatch('eloquent.updated: '.$_staff::class, $_staff);
            }
        }
    }
}
