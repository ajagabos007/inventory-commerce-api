<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PopulateInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use sqlite :memory: for this test
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }

    public function test_it_populates_inventory_correctly()
    {
        // Setup
        $store = Store::factory()->create(['name' => 'Test Store']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-123'
        ]);

        // Create CSV
        $csvContent = "SKU,Item,Qty\nTEST-SKU-123,Test Item,10\n";
        $fileName = 'test_inventory.csv';
        $filePath = public_path('inventory/' . $fileName);
        
        if (!File::exists(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }
        File::put($filePath, $csvContent);

        // Run Command
        $this->artisan('inventory:populate', [
            'file' => $fileName,
            'store' => 'Test Store',
        ])
        ->assertExitCode(0);

        // Verify
        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'status' => InventoryStatus::AVAILABLE->value,
        ]);

        // Cleanup
        File::delete($filePath);
    }

    public function test_it_overrides_inventory_when_flag_is_set()
    {
        // Setup
        $store = Store::factory()->create(['name' => 'Test Store']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-123'
        ]);
        
        // Initial inventory
        Inventory::create([
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'status' => InventoryStatus::AVAILABLE->value,
        ]);

        // Create CSV
        $csvContent = "SKU,Item,Qty\nTEST-SKU-123,Test Item,10\n";
        $fileName = 'test_inventory_override.csv';
        $filePath = public_path('inventory/' . $fileName);
        
        if (!File::exists(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }
        File::put($filePath, $csvContent);

        // Run Command with --override
        $this->artisan('inventory:populate', [
            'file' => $fileName,
            'store' => 'Test Store',
            '--override' => true,
        ])
        ->assertExitCode(0);

        // Verify (Should be 10, not 15)
        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
        ]);

        // Cleanup
        File::delete($filePath);
    }

    public function test_it_adds_to_inventory_when_flag_is_not_set()
    {
        // Setup
        $store = Store::factory()->create(['name' => 'Test Store']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'TEST-SKU-123'
        ]);
        
        // Initial inventory
        Inventory::create([
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
            'status' => InventoryStatus::AVAILABLE->value,
        ]);

        // Create CSV
        $csvContent = "SKU,Item,Qty\nTEST-SKU-123,Test Item,10\n";
        $fileName = 'test_inventory_add.csv';
        $filePath = public_path('inventory/' . $fileName);
        
        if (!File::exists(dirname($filePath))) {
            File::makeDirectory(dirname($filePath), 0755, true);
        }
        File::put($filePath, $csvContent);

        // Run Command without override
        $this->artisan('inventory:populate', [
            'file' => $fileName,
            'store' => 'Test Store',
        ])
        ->assertExitCode(0);

        // Verify (Should be 15)
        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'product_variant_id' => $variant->id,
            'quantity' => 15,
        ]);

        // Cleanup
        File::delete($filePath);
    }
}
