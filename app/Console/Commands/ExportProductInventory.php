<?php

namespace App\Console\Commands;

use App\Mail\ProductInventoryExport as ProductInventoryExportMail;
use App\Models\Inventory;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use ZipArchive;

class ExportProductInventory extends Command
{
    protected $signature = 'app:export-product-inventory
                            {--store= : Store ID or slug to filter (default: warehouse)}
                            {--all-stores : Export from all stores instead of just warehouse}
                            {--email=* : Email address(es) to send the CSV}
                            {--zip : Create zip file instead of plain CSV}
                            {--filename= : Custom filename for the export (default: product-inventory-{date}.csv)}';

    protected $description = 'Export product inventory to CSV with optional email delivery and zip compression';

    private int $totalProducts = 0;

    private int $totalRecords = 0;

    private array $stores = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📦 Starting Product Inventory Export...');
        $this->newLine();

        // Determine stores to export
        $stores = $this->determineStores();

        if ($stores->isEmpty()) {
            $this->error('❌ No stores found to export.');

            return Command::FAILURE;
        }

        $this->stores = $stores->pluck('name', 'id')->toArray();
        $this->info('🏪 Exporting from stores: '.implode(', ', $this->stores));
        $this->newLine();

        // Generate filename
        $filename = $this->generateFilename();
        $csvPath = "exports/{$filename}";

        // Create CSV
        $this->info('📝 Generating CSV...');
        $fullPath = $this->generateCsv($stores, $csvPath);

        if (! $fullPath) {
            $this->error('❌ Failed to generate CSV.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->displaySummary($fullPath);

        // Handle zip if requested
        if ($this->option('zip')) {
            $zipPath = $this->createZipFile($fullPath, $filename);
            if ($zipPath) {
                $fullPath = $zipPath;
                $this->info("📦 Zip file created: {$zipPath}");
            }
        }

        // Handle email if requested
        $emails = $this->option('email');
        if (! empty($emails)) {
            $this->sendEmail($emails, $fullPath, $filename);
        }

        $this->newLine();
        $this->info('✅ Export completed successfully!');
        $this->info("📁 File location: ".Storage::disk('public')->path($fullPath));
        $this->info("🌐 Public URL: ".Storage::disk('public')->url($fullPath));

        return Command::SUCCESS;
    }

    /**
     * Determine which stores to export from
     */
    private function determineStores()
    {
        // All stores option
        if ($this->option('all-stores')) {
            return Store::all();
        }

        // Specific store option
        if ($storeIdentifier = $this->option('store')) {
            $store = Store::where('id', $storeIdentifier)
                ->orWhere('slug', $storeIdentifier)
                ->first();

            if (! $store) {
                $this->warn("⚠️  Store '{$storeIdentifier}' not found. Using warehouse instead.");

                return Store::warehouses()->get();
            }

            return collect([$store]);
        }

        // Default: warehouse
        $warehouses = Store::warehouses()->get();

        if ($warehouses->isEmpty()) {
            $this->warn('⚠️  No warehouse found. Using all stores instead.');

            return Store::all();
        }

        return $warehouses;
    }

    /**
     * Generate filename for export
     */
    private function generateFilename(): string
    {
        $customFilename = $this->option('filename');

        if ($customFilename) {
            // Ensure .csv extension
            if (! str_ends_with($customFilename, '.csv')) {
                $customFilename .= '.csv';
            }

            return $customFilename;
        }

        $date = now()->format('Y-m-d_His');

        return "product-inventory-{$date}.csv";
    }

    /**
     * Generate CSV file
     */
    private function generateCsv($stores, string $csvPath): ?string
    {
        try {
            // Ensure exports directory exists
            Storage::disk('public')->makeDirectory('exports');

            // Create CSV writer
            $csv = Writer::createFromPath(
                Storage::disk('public')->path($csvPath),
                'w+'
            );

            // Write header
            $csv->insertOne([
                'Product Name',
                'SKU',
                'Store Name',
                'Inventory Quantity',
                'Image URL',
            ]);

            // Get inventories with progress bar
            $storeIds = $stores->pluck('id')->toArray();
            $inventories = Inventory::withoutGlobalScope('store')
                ->whereIn('store_id', $storeIds)
                ->with(['productVariant.product.image', 'store'])
                ->get();

            $bar = $this->output->createProgressBar($inventories->count());
            $bar->start();

            $productIds = [];

            foreach ($inventories as $inventory) {
                $product = $inventory->productVariant->product;
                $productIds[$product->id] = true;

                // Get image URL
                $imageUrl = '';
                if ($product->image) {
                    $imageUrl = $product->image->url;
                }

                // Write row
                $csv->insertOne([
                    $product->name,
                    $inventory->productVariant->sku ?? 'N/A',
                    $inventory->store->name,
                    $inventory->quantity,
                    $imageUrl,
                ]);

                $this->totalRecords++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $this->totalProducts = count($productIds);

            return $csvPath;

        } catch (\Exception $e) {
            $this->error("Error generating CSV: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Create zip file containing the CSV
     */
    private function createZipFile(string $csvPath, string $csvFilename): ?string
    {
        // Check if ZipArchive is available
        if (! class_exists('ZipArchive')) {
            $this->error('❌ ZipArchive PHP extension is not installed.');
            $this->warn('   To enable zip functionality, install the php-zip extension:');
            $this->warn('   sudo apt-get install php-zip  (or)  sudo yum install php-zip');
            $this->warn('   Keeping CSV format instead.');

            return null;
        }

        try {
            $zipFilename = str_replace('.csv', '.zip', $csvFilename);
            $zipPath = "exports/{$zipFilename}";
            $fullZipPath = Storage::disk('public')->path($zipPath);

            $zip = new ZipArchive;
            if ($zip->open($fullZipPath, ZipArchive::CREATE) === true) {
                $zip->addFile(
                    Storage::disk('public')->path($csvPath),
                    $csvFilename
                );
                $zip->close();

                // Delete the original CSV file
                Storage::disk('public')->delete($csvPath);

                return $zipPath;
            }

            $this->warn('⚠️  Failed to create zip file. Keeping CSV format.');

            return null;

        } catch (\Exception $e) {
            $this->error("Error creating zip: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Send email with attachment
     */
    private function sendEmail(array $emails, string $filePath, string $filename): void
    {
        $this->newLine();
        $this->info('📧 Sending email(s)...');

        try {
            $fullPath = Storage::disk('public')->path($filePath);
            $isZip = str_ends_with($filename, '.zip');

            foreach ($emails as $email) {
                Mail::to($email)->send(
                    new ProductInventoryExportMail(
                        $fullPath,
                        $filename,
                        $this->totalProducts,
                        $this->totalRecords,
                        $this->stores,
                        $isZip
                    )
                );

                $this->info("  ✅ Email sent to: {$email}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: {$e->getMessage()}");
        }
    }

    /**
     * Display export summary
     */
    private function displaySummary(string $filePath): void
    {
        $fileSize = Storage::disk('public')->size($filePath);
        $fileSizeFormatted = $this->formatBytes($fileSize);

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Export Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("📦 Total Products: {$this->totalProducts}");
        $this->info("📋 Total Records: {$this->totalRecords}");
        $this->info("🏪 Stores: ".count($this->stores));
        $this->info("📁 File Size: {$fileSizeFormatted}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
