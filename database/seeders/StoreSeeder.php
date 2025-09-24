<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\Store;
use App\Models\User;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = [
            'name' => $name = 'CBM MALL HQ',
            'slug' => SlugService::createSlug(Store::class, 'slug', $name),
            'address' => 'Maitama, Abuja',
            'is_warehouse' => true,
        ];

        $stores = Store::factory(1)->make()->toArray();

        $stores[] = Store::factory()->make($warehouse)->toArray();

        $start_time = now();

        Store::upsert(
            $stores,
            uniqueBy: ['name', 'address'],
            update: ['name', 'address']
        );

        $end_time = now();

        $stores = Store::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])
            ->lazy();

        foreach ($stores as $store) {
            if ($store->created_at == $store->updated_at) {
                Event::dispatch('eloquent.created: '.$store::class, $store);
            } else {
                Event::dispatch('eloquent.updated: '.$store::class, $store);
            }
        }

        $store = Store::warehouses()->first();

        if (is_null($store)) {
            return;
        }
        $users = User::whereDoesntHave('staff')
            ->whereIn('email', ['admin@cbm-mall.com'])
            ->get();
        $staff = collect();
        foreach ($users as $_user) {
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
        $staff = \App\Models\Staff::whereBetween('created_at', [
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
