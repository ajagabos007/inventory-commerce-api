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
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_id')
                ->references('id')
                ->on('attributes')
                ->onDelete('cascade');
            $table->text('value');

            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);
            $table->unique(['attribute_id', 'value'], 'unique_attribute_value');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
