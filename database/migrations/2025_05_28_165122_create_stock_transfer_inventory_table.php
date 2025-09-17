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
        Schema::create('stock_transfer_inventory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_id')
                ->constrained('inventories')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignUuid('stock_transfer_id')
                ->constrained('stock_transfers')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->integer('quantity')->default(1);
            $table->timestamps();
            $table->unique(['inventory_id', 'stock_transfer_id'], 'unique_inventory_stock_transfer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_inventory');
    }
};
