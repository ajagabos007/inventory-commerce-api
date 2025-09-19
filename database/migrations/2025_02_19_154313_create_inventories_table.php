<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_variant_id')
                ->constrained('product_variants')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignUuid('store_id')
                ->constrained('stores')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('serial_number')->nullable()->unique();
            $table->string('batch_number')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('status')->default('available');
            $table->string('condition_status')->default('new');
            $table->date('received_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);

            $table->unique(['product_variant_id', 'store_id'], 'unique_store_product_variant_inventory');
            $table->index(['product_variant_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index(['serial_number']); // Already unique, but good for lookups
            $table->index(['batch_number']);
            $table->index(['condition_status']);
            $table->index(['received_date']);
            $table->index(['warranty_expiry_date']);
            $table->index(['status', 'quantity']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
