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

            $table->integer('quantity')->default(1);
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);
            $table->unique(['product_variant_id', 'store_id'], 'unique_store_product_variant_inventory');

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
