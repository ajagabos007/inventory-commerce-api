<?php

namespace App\Console\Commands;

use App\Enums\InventoryStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;

class ImportProducts extends Command
{
    protected $signature = 'app:import-products
                            {--file= : CSV file name (default: products.csv)}
                            {--images-dir=images : Images directory name}
                            {--dry-run : Run without saving to database}';

    protected $description = 'Import products from a CSV file and attach images';

    private ?Store $warehouse = null;
    private int $successCount = 0;
    private int $errorCount = 0;
    private array $errors = [];
    private string $imagesDir = 'images';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = $this->option('file') ?? 'products.csv';
        $imagesDir = $this->option('images-dir') ?? 'images';

        // Use 'imports' directory instead of 'products'
        $path = storage_path("app/private/imports/{$file}");

        if (!$this->validateFile($path)) {
            return Command::FAILURE;
        }

        if (!$this->initializeWarehouse()) {
            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be saved');
        }

        try {
            $csv = $this->readCsv($path);
            $this->processRecords($csv, $imagesDir);
        } catch (CsvException $e) {
            $this->error("CSV Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $this->displaySummary();

        return Command::SUCCESS;
    }

    /**
     * Validate that the CSV file exists
     */
    private function validateFile(string $path): bool
    {
        if (!file_exists($path)) {
            $this->error("❌ CSV file not found: {$path}");
            return false;
        }

        $this->info("📄 Reading CSV: {$path}");
        return true;
    }

    /**
     * Initialize warehouse for inventory
     */
    private function initializeWarehouse(): bool
    {
        $this->warehouse = Store::warehouses()->first();

        if (!$this->warehouse) {
            $this->error("❌ No warehouse found. Please create a warehouse first.");
            return false;
        }

        $this->info("🏭 Using warehouse: {$this->warehouse->name} (ID: {$this->warehouse->id})");
        return true;
    }

    /**
     * Read and configure CSV reader
     */
    private function readCsv(string $path): Reader
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        return $csv;
    }

    /**
     * Process all CSV records
     */
    private function processRecords(Reader $csv, string $imagesDir): void
    {
        $this->imagesDir = $imagesDir;

        $records = iterator_to_array($csv->getRecords());
        $bar = $this->output->createProgressBar(count($records));
        $bar->start();

        foreach ($records as $index => $record) {
            $this->processRecord($record, $index + 2); // +2 for header row and 1-indexed
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Process a single CSV record
     */
    private function processRecord(array $record, int $rowNumber): void
    {
        try {
            DB::beginTransaction();

            // Skip if dry-run
            if ($this->option('dry-run')) {
                $this->info("Would import: {$record['Name']}");
                DB::rollBack();
                return;
            }

            // Get or create category
            $category = $this->getOrCreateCategory($record['Category'] ?? 'Uncategorized');

            // Get or create attribute values
            $attributeValues = $this->getAttributeValues($record);

            // Check if record contains multiple products
            if ($this->hasMultipleProducts($record)) {
                $this->importMultipleProducts($record, $attributeValues, $category->id);
            } else {
                $this->importSingleProduct($record, $attributeValues, $category->id);
            }

            DB::commit();
            $this->successCount++;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorCount++;
            $error = "Row {$rowNumber} - ".($record['Name'] ?? 'Unknown').": {$e->getMessage()}";
            $this->errors[] = $error;
            $this->error("❌ {$error}");
        }
    }

    /**
     * Get or create category
     */
    private function getOrCreateCategory(string $name): Category
    {
        return Category::firstOrCreate(['name' => $name]);
    }

    /**
     * Get attribute values for Type and Brand
     */
    private function getAttributeValues(array $record): array
    {
        $attributeValueIds = [];

        // Type attribute
        $type = Attribute::firstOrCreate(['name' => 'Type']);
        $typeValue = AttributeValue::firstOrCreate(
            ['attribute_id' => $type->id, 'value' => $record['Type'] ?? 'General']
        );
        $attributeValueIds[] = $typeValue->id;

        // Brand attribute
        $brand = Attribute::firstOrCreate(['name' => 'Brand']);
        $brandValue = AttributeValue::firstOrCreate(
            ['attribute_id' => $brand->id, 'value' => $record['Brand'] ?? 'Generic']
        );
        $attributeValueIds[] = $brandValue->id;

        return $attributeValueIds;
    }

    /**
     * Check if record contains multiple products separated by <br>
     */
    private function hasMultipleProducts(array $record): bool
    {
        return str_contains($record['Name'] ?? '', '<br>');
    }

    /**
     * Import multiple products from a single CSV row
     */
    private function importMultipleProducts(array $record, array $attributeValues, string $categoryId): void
    {
        $parsed = $this->parseMultipleProducts($record);

        if (empty($parsed)) {
            return;
        }

        // First product becomes the main product
        $mainProduct = array_shift($parsed);
        $this->importProductRecord($mainProduct, $attributeValues, $categoryId);

        // Rest become variants
        foreach ($parsed as $variant) {
            $this->info("  ➕ Adding variant: {$variant['Name']}");
            $this->importProductRecord($mainProduct, $attributeValues, $categoryId, [$variant]);
        }
    }

    /**
     * Parse multiple products separated by <br>
     */
    private function parseMultipleProducts(array $record): array
    {
        $products = $this->splitAndClean($record['Name'] ?? '');
        $prices = $this->splitAndParseFloat($record['Price'] ?? '0');
        $costs = $this->splitAndParseFloat($record['Cost'] ?? '0');
        $quantities = $this->splitAndParseInt($record['Quantity'] ?? '0');

        $result = [];
        foreach ($products as $index => $name) {
            $result[] = [
                'Name' => $name,
                'Code' => $record['Code'] ?? null,
                'Price' => $prices[$index] ?? 0,
                'Cost' => $costs[$index] ?? 0,
                'Quantity' => $quantities[$index] ?? 0,
                'Image' => $record['Image'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Split string by <br> and clean
     */
    private function splitAndClean(string $value): array
    {
        return array_filter(
            explode('<br>', $value),
            fn($v) => !blank($v)
        );
    }

    /**
     * Split and parse float values
     */
    private function splitAndParseFloat(string $value): array
    {
        $parts = $this->splitAndClean($value);
        return array_map(
            fn($v) => (float) filter_var($v, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            $parts
        );
    }

    /**
     * Split and parse integer values
     */
    private function splitAndParseInt(string $value): array
    {
        $parts = $this->splitAndClean($value);
        return array_map(
            fn($v) => (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT),
            $parts
        );
    }

    /**
     * Import single product
     */
    private function importSingleProduct(array $record, array $attributeValues, string $categoryId): void
    {
        $this->importProductRecord($record, $attributeValues, $categoryId);
    }

    /**
     * Import product record with optional variants
     */
    private function importProductRecord(
        array $record,
        array $attributeValues,
        string $categoryId,
        array $variantsRecord = []
    ): void {

        $now = now();
        // Create or get product
        $product = Product::firstOrCreate(
            ['name' => $record['Name'] ?? 'Unnamed Product'],
            ['name' => $record['Name']]
        );


//        if($product->created_at->lt($now)) {
//           $this->warn("Product '{$product->name}' already exists. Skipping creation.");
//           return;
//        }


        // Sync relationships
        $product->attributeValues()->syncWithoutDetaching($attributeValues);
        $product->categories()->sync([$categoryId]);

        // Attach main product image
        $this->attachImage($product, $record);

        // Create variants
        if (!empty($variantsRecord)) {
            $this->createVariants($product, $variantsRecord);
        } else {
            $this->createMainVariant($product, $record);
        }
    }

    /**
     * Create product variants
     */
    private function createVariants(Product $product, array $variantsRecord): void
    {
        foreach ($variantsRecord as $variantRecord) {
            $variant = $this->createVariant($product, $variantRecord);
            $this->createInventory($variant, $variantRecord);
            $this->attachImage($variant, $variantRecord);
        }
    }

    /**
     * Create main variant for simple product
     */
    private function createMainVariant(Product $product, array $record): void
    {
        $variant = $this->createVariant($product, $record);
        $this->createInventory($variant, $record);
        $this->attachImage($variant, $record);
    }

    /**
     * Create a single variant
     * @param Product $product
     * @param array $record
     * @return ProductVariant|Model
     */
    private function createVariant(Product $product, array $record): ProductVariant | Model
    {
        return $product->variants()->firstOrCreate(
            ['sku' => $record['Code'] ?? null],
            [
                'name' => $record['Name'] ?? null,
                'price' => (float) ($record['Price'] ?? 0),
                'cost_price' => (float) ($record['Cost'] ?? 0),
                'sku' => $record['Code'] ?? null,
            ]
        );
    }

    /**
     * Create inventory for variant
     */
    private function createInventory(ProductVariant $variant, array $record): void
    {
        $quantity = $this->parseQuantity($record['Quantity'] ?? 0);
        $status = $quantity > 0
            ? InventoryStatus::AVAILABLE->value
            : InventoryStatus::OUT_OF_STOCK->value;

        $variant->inventories()->updateOrCreate(
            ['store_id' => $this->warehouse->id],
            [
                'quantity' => $quantity,
                'status' => $status,
            ]
        );
    }

    /**
     * Parse quantity to integer
     */
    private function parseQuantity(mixed $quantity): int
    {
        if (is_string($quantity)) {
            return (int) filter_var($quantity, FILTER_SANITIZE_NUMBER_INT);
        }
        return (int) $quantity;
    }

    /**
     * Attach image to model
     */
    private function attachImage($model, array $record): void
    {
        if (blank($record['Image'])) {
            return;
        }

        if($model->images()->where('name', $record['Image'])->exists()) {
            $this->warn(" Image '{$record['Image']}' already exists.");
            return;
        }
        // Use configurable images directory
        $imagePath = "imports/{$this->imagesDir}/{$record['Image']}";

        if (!Storage::disk('local')->exists($imagePath)) {
            $this->warn("  ⚠️  Image not found: {$imagePath}");
            return;
        }

        try {
            $fileContent = Storage::disk('local')->get($imagePath);
            $model->attachFileContent($fileContent, ['file_name' => $record['Image']]);
            $this->line("  ✅ Image attached: {$record['Image']}");
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Failed to attach image: {$e->getMessage()}");
        }
    }

    /**
     * Display import summary
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Import Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("✅ Success: {$this->successCount}");

        if ($this->errorCount > 0) {
            $this->error("❌ Errors: {$this->errorCount}");

            if (!empty($this->errors)) {
                $this->newLine();
                $this->error('Error Details:');
                foreach ($this->errors as $error) {
                    $this->error("  • {$error}");
                }
            }
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Offer to clean up imports if successful
        if ($this->successCount > 0 && !$this->option('dry-run')) {
            $this->newLine();

            if ($this->confirm('🗑️  Would you like to clean up the import files?', false)) {
                $this->newLine();

                $action = $this->choice(
                    'How would you like to clean up?',
                    ['Archive (recommended)', 'Delete permanently'],
                    0
                );

                if ($action === 'Archive (recommended)') {
                    $this->call('app:cleanup-imports', ['--archive' => true, '--force' => true]);
                } else {
                    if ($this->confirm('⚠️  Are you sure you want to permanently delete the files?', false)) {
                        $this->call('app:cleanup-imports', ['--force' => true]);
                    }
                }
            } else {
                $this->info('💡 Tip: Run "php artisan app:cleanup-imports --archive" to clean up later');
            }
        }
    }
}
