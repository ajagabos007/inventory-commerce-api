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
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);

        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignUuid('parent_id')
                ->nullable()->constrained('categories')
                ->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
