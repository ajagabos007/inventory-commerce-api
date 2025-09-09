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
        Schema::create('attributables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attribute_value_id');
            $table->foreign('attribute_value_id')
                ->references('id')
                ->on('attribute_values')
                ->cascadeOnDelete();

            $table->uuidMorphs('attributable');
            $table->unique(['attribute_value_id', 'attributable_id', 'attributable_type'], 'unique_attributable');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributables');
    }
};
