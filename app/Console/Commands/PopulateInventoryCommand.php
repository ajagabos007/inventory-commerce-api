<?php

namespace App\Console\Commands;

use App\Enums\InventoryStatus;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PopulateInventoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:populate 
                            {file : Path to CSV file relative to public/inventory} 
                            {store : Store name} 
                            {--override : Override existing quantity} 
                            {--force : Alias for override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate store inventory from a CSV file';

    //php artisan inventory:populate {file} {store} [--override|--force]

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fileName = $this->argument('file');
        $storeName = $this->argument('store');
        $override = $this->option('override') || $this->option('force');

        $filePath = public_path('inventory/' . $fileName);

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $store = Store::where('name', $storeName)->first();

        if (!$store) {
            $this->error("Store not found: {$storeName}");
            return 1;
        }

        $this->info("Processing file: {$fileName} for store: {$store->name}");
        $this->info("Mode: " . ($override ? 'Override' : 'Add'));

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file); // Skip header

        // Map header columns to indices if needed, but for now assuming fixed structure based on wuse.csv
        // Structure: SKU, Item, Qty, ...

        $processed = 0;
        $skipped = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file)) !== false) {
                // Ensure row has enough columns
                if (count($row) < 3) {
                    continue;
                }

                $sku = trim($row[0]);
                $qty = (int) preg_replace('/[^0-9]/', '', $row[2]); // Clean quantity input

                if (empty($sku)) {
                    // $this->warn("Skipping row with empty SKU: " . implode(',', $row));
                    $skipped++;
                    continue;
                }

                $variant = ProductVariant::where('sku', $sku)->first();

                if (!$variant) {
                    $this->warn("Product Variant not found for SKU: {$sku}");
                    $skipped++;
                    continue;
                }

                // Find or create inventory
                $inventory = Inventory::where('store_id', $store->id)
                    ->where('product_variant_id', $variant->id)
                    ->first();

                if (!$inventory) {
                    $inventory = new Inventory();
                    $inventory->store_id = $store->id;
                    $inventory->product_variant_id = $variant->id;
                    $inventory->quantity = 0;
                    $inventory->status = InventoryStatus::OUT_OF_STOCK->value;
                }

                $oldQty = $inventory->quantity;

                if ($override) {
                    $inventory->quantity = $qty;
                } else {
                    $inventory->quantity += $qty;
                }

                if ($inventory->quantity > 0) {
                    $inventory->status = InventoryStatus::AVAILABLE->value;
                } else {
                    $inventory->status = InventoryStatus::OUT_OF_STOCK->value;
                }

                $inventory->save();
                
                // Update product metadata if needed (ProductVariant::updateAvailableQuantity logic might handle this via observers if they exist, 
                // but let's check if we need to trigger anything manually. The model has `updateAvailableQuantity` but it's not called automatically on save usually unless observed)
                // Looking at ProductVariant model, it has `updateAvailableQuantity` method.
                // And Inventory is a Pivot, so standard observers might be tricky, but let's rely on standard save first.
                // Actually, let's manually trigger the update on the variant to be safe/sure.
                $variant->updateAvailableQuantity();

                $updated++;
                $processed++;
                
                $this->output->write(".");
                if ($processed % 50 == 0) {
                    $this->output->writeln("");
                }
            }

            DB::commit();
            fclose($file);

            $this->newLine();
            $this->info("Inventory population completed.");
            $this->info("Processed: {$processed}");
            $this->info("Updated: {$updated}");
            $this->info("Skipped: {$skipped}");

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $this->error("An error occurred: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
