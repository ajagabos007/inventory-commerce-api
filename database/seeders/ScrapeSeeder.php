<?php

namespace Database\Seeders;

use App\Models\Scrape;
use Illuminate\Database\Seeder;

class ScrapeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $scrapes = Scrape::factory()->count(25)->make()->toArray();

        $start_time = now();
        Scrape::upsert(
            $scrapes,
            uniqueBy: ['inventory_id', 'customer_id', 'type'],
            update: []
        );
        $end_time = now();

        $scrapes = Scrape::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])
            ->lazy();
        foreach ($scrapes as $scrape) {
            if ($scrape->created_at == $scrape->updated_at) {
                event('eloquent.created: '.$scrape::class, $scrape);
            } else {
                event('eloquent.updated: '.$scrape::class, $scrape);
            }
        }
    }
}
