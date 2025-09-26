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
                ->constrained('inventories')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2);
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
