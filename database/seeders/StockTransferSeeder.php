<?php

namespace Database\Seeders;

use App\Models\StockTransfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class StockTransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stock_transfers = StockTransfer::factory(10)->make()->toArray();

        $start_time = now();
        StockTransfer::upsert(
            $stock_transfers,
            uniqueBy: ['reference_no'],
            update: []
        );

        $end_time = now();

        $stock_transfers = StockTransfer::whereBetween('created_at', [
            $start_time->toDateTimeString(),  $end_time->toDateTimeString(),
        ])
            ->lazy();

        foreach ($stock_transfers as $stock_transfer) {
            if ($stock_transfer->created_at == $stock_transfer->updated_at) {
                Event::dispatch('eloquent.created: '.$stock_transfer::class, $stock_transfer);
            } else {
                Event::dispatch('eloquent.updated: '.$stock_transfer::class, $stock_transfer);
            }
        }
    }
}
