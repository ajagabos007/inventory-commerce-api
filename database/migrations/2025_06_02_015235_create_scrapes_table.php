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
        Schema::create('scrapes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_id')
                ->constrained('inventories')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->foreignUuid('customer_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->foreignUuid('staff_id')
                ->nullable()
                ->constrained('staff')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->text('comment')->nullable();
            $table->string('type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);
            $table->unique(['inventory_id', 'customer_id', 'type'], 'unique_scrape_inventory_customer_type');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrapes');
    }
};
