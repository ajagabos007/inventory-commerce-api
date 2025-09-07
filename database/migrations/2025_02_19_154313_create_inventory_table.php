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
        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->constrained('products')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignUuid('store_id')
                ->constrained('stores')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->integer('quantity')->default(1);

            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);
            $table->unique(['product_id', 'store_id'], 'unique_item_store_inventory');

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
