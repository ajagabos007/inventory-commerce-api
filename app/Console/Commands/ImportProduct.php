<?php

namespace App\Console\Commands;

use App\Enums\InventoryStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use League\Csv\Reader;
use Illuminate\Console\Command;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\AttributeValue;

class ImportProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-product {--file= : CSV file path inside storage/app/private}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from a CSV file and attach images';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->option('file') ?? 'products.csv';
        $path = storage_path("app/private/products/{$file}");

        if (!file_exists($path)) {
            $this->error("CSV file not found: {$path}");
            return Command::FAILURE;
        }

        $this->info("Reading CSV: {$path}");

        // Read CSV
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0); // first row = headers

        $bar = $this->output->createProgressBar(iterator_count($csv));
        $bar->start();

        $warehouse = Store::warehouses()->first();


        foreach ($csv->getRecords() as $record) {

            try {
                DB::beginTransaction();

                $category = Category::firstOrCreate(
                    ['name' => $record['Category'] ?? 'Uncategorized'],
                );


                $type = Attribute::firstOrCreate(
                    ['name' => 'Type'],
                );

                $typeValue = AttributeValue::firstOrCreate(
                    ['attribute_id' => $type->id, 'value' => $record['Type'] ?? 'General'],
                );

                $brand = Attribute::firstOrCreate(
                    ['name' => 'Brand'],
                );

                $brandValue = AttributeValue::firstOrCreate(
                    ['attribute_id' => $brand->id, 'value' => $record['Brand'] ?? 'Generic'],
                );

               if(str_contains($record['Name'], "<br>")) {
                   $this->warn("Skipping brand name: {$record['Name']}");
                   $products = explode("<br>", $record['Name']);
                   $products = array_filter($products, fn($value) => !blank($value));

                   $prices = explode("<br>", $record['Price']);
                   $prices = array_filter($prices, fn($value) => !blank($value));
                   $prices = array_map(fn($value) => (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), $prices);

                   $costs = explode("<br>", $record['Cost']);
                   $costs = array_filter($costs, fn($value) => !blank($value));
                   $costs = array_map(fn($value) => (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), $costs);

                   $quantities = explode("<br>", $record['Quantity']);
                   $quantities = array_filter($quantities, fn($value) => !blank($value));
                   $quantities = array_map(fn($value) => (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT), $quantities);

//                   foreach($products as $index => $productName) {
//                       $product = Product::updateOrCreate(
//                           ['name' => $productName ?? 'Unnamed Product'],
//                           [
//                               'name' => $productName ?? null,
//                           ]
//                       );
//                   }
                   continue;
               }

                $product = Product::updateOrCreate(
                    ['name' => $record['Name'] ?? 'Unnamed Product'],
                    [
                        'name'        => $record['Name'] ?? null,
                    ]
                );

                $product->attributeValues()->syncWithoutDetaching([$typeValue, $brandValue]);

                $product->categories()->sync([$category]);

                $validated = $product->toArray();
                $validated['price'] =(float) ($record['Price'] ?? 0);
                $validated['cost_price'] =(float) ($record['Cost'] ?? 0);

                $variant = $product->variants()->updateOrCreate(
                    ['name' => $validated['name'] ?? 'Unnamed Product'],
                    $validated
                );

                if ($warehouse) {
                    $quantity = $record['Quantity'] ?? 0;
                    if(is_string($quantity)) {
                        $quantity = (int) filter_var($record['Quantity'], FILTER_SANITIZE_NUMBER_INT);

                    }

                    $status = $quantity > 0 ? InventoryStatus::AVAILABLE->value : InventoryStatus::OUT_OF_STOCK->value;
                    $validated['quantity'] = $quantity;
                    $validated['store_id'] = $warehouse->id;
                    $variant->inventories()->firstOrCreate(
                        ['store_id' => $warehouse->id],
                        $validated
                    );
                }else {
                    $this->warn("No warehouse found. Skipping inventory for code {$record['Code']} \n");
                    continue;
                }

                // Attach image if exists
                if (!blank($record['Image'])) {
                    $imagePath = "products/images/{$record['Image']}";

                    if (Storage::disk('local')->exists($imagePath)) {
                        $this->line("\nAttaching image for SKU {$record['Code']}: {$imagePath}");
                        $fileContent =Storage::disk('local')->get($imagePath);
                        $options['file_name'] = $record['Image'];
                        $product->attachFileContent($fileContent,$options);

                    } else {
                        $this->warn("Image not found for SKU {$record['Code']}: {$imagePath}");
                    }
                }
                else{
                   $this->warn("No image found for Code {$record['Code']}");
                }

                DB::commit();

            } catch (\Exception $e) {
                $this->error("Error importing Code {$record['Code']}: {$e->getMessage()}");
                DB::rollBack();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nImport finished!");

        return Command::SUCCESS;

    }
}
