<?php

namespace App\Console\Commands;

use App\Facades\Cart;
use App\Models\Category;
use App\Models\DailyGoldPrice;
use Illuminate\Console\Command;

class SeedDailyGoldPrice extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gold:seed-daily-prices 
                            {--default-price=1000 : Fallback price if no previous record exists}';

    /**
     * The console command description.
     */
    protected $description = 'Seed today\'s gold prices for categories that do not yet have a price entry';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        $defaultPrice = (float) $this->option('default-price');

        $categoriesMissingToday = Category::withoutTodayGoldPrice()->pluck('id');

        if ($categoriesMissingToday->isEmpty()) {
            $this->info("✅ All categories already have today's gold price set.");

            return Command::SUCCESS;
        }

        $latestPrices = DailyGoldPrice::select('daily_gold_prices.*')
            ->joinSub(
                DailyGoldPrice::selectRaw('MAX(recorded_on) as recorded_on, category_id')
                    ->whereIn('category_id', $categoriesMissingToday)
                    ->groupBy('category_id'),
                'latest',
                function ($join) {
                    $join->on('daily_gold_prices.category_id', '=', 'latest.category_id')
                        ->on('daily_gold_prices.recorded_on', '=', 'latest.recorded_on');
                }
            )
            ->get()
            ->keyBy('category_id');

        $entries = $categoriesMissingToday->map(function ($categoryId) use ($latestPrices, $today, $defaultPrice) {
            $latest = $latestPrices->get($categoryId);

            return [
                'category_id' => $categoryId,
                'price_per_gram' => $latest?->price_per_gram ?? $defaultPrice,
                'recorded_on' => $today,
            ];
        });

        DailyGoldPrice::upsert(
            $entries->toArray(),
            ['category_id', 'recorded_on'],
            ['price_per_gram']
        );

        $entries_count = $entries->count();

        if ($entries_count > 0) {
            Cart::clearAll();
        }
        $this->info("💰 Seeded daily gold prices for {$entries_count} category(ies) on {$today}.");

        return Command::SUCCESS;
    }
}
