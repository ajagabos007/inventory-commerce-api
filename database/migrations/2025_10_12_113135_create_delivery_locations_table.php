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
        Schema::create('delivery_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')
                ->constrained('stores');
            $table->foreignId('country_id');
            $table->foreignId('state_id');
            $table->foreignId('city_id')
                ->nullable()
                ->constrained('cities')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->integer('estimated_delivery_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
    }
};
