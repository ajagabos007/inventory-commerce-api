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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('sku')->unique();
            $table->string('name')->nullable();
            $table->text('barcode')->nullable()->unique();
            $table->decimal('price', total: 8, places: 2);
            $table->decimal('compare_price', total: 8, places: 2)->nullable();
            $table->decimal('cost_price', total: 8, places: 2)->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
