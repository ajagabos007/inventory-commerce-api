<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-metadata {--store=* : Specify one or more store IDs (comma separated) to process only those stores.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and update metadata quantities for all products and variants, scoped per store.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $storeIds = $this->option('store');

        $stores = $storeIds
            ? Store::whereIn('id', (array) $storeIds)->get()
            : Store::all();

        if ($stores->isEmpty()) {
            $this->warn('No stores found to process.');
            return Command::SUCCESS;
        }

        $this->info('Starting metadata update...');
        $startTime = microtime(true);

        foreach ($stores as $store) {
            $this->newLine();
            $this->info("🏬 Processing store: {$store->name} ({$store->id})");
            Log::info("Starting metadata update for store: {$store->id} ({$store->name})");

            set_current_store($store);

            $processed = 0;
            $failed = 0;

            $variants = ProductVariant::lazy();

            $bar = $this->output->createProgressBar($variants->count() ?: 0);
            $bar->start();

            foreach ($variants as $variant) {
                try {
                    $variant->updateAvailableQuantity();
                    $variant->product->updateAvailableQuantity();

                    $this->line(" → Updated: {$variant->product->name} ({$variant->name})", 'v');
                    Log::info("Updated variant: {$variant->id} | Product: {$variant->product->name} | Variant: {$variant->name}");

                    $processed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::error("Failed updating variant [{$variant->id}] for store [{$store->id}]: {$e->getMessage()}");
                    $this->error(" ⚠️  Error on variant [{$variant->id}] ({$variant->name}): {$e->getMessage()}");
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Finished store: {$store->name} | Processed: {$processed}, Failed: {$failed}");
            Log::info("Completed store [{$store->id}] | Processed: {$processed}, Failed: {$failed}");
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine(2);
        $this->info("🎯 All stores processed successfully in {$duration} seconds.");

        return Command::SUCCESS;
    }
}
