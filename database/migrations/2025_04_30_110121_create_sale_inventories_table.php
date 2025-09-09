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
        Schema::create('sale_inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')
                ->constrained('sales')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('inventory_id')
                ->constrained('inventory')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->decimal('weight', 8, 2)
                ->nullable()
                ->comment('gram');

            $table->decimal('price_per_gram', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2);
            //            $table->foreignUuid('daily_gold_price_id')
            //                ->nullable()
            //                ->constrained('daily_gold_prices')
            //                ->onUpdate('cascade')
            //                ->onDelete('set null');
            $table->json('metadata')->nullable();

            $table->unique(['sale_id', 'inventory_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_inventories');
    }
};
