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
        Schema::create('wish_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('item');
            $table->string('name');
            $table->decimal('price', 8, 2)->nullable();
            $table->string('session_token')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->foreignUuid('store_id')
                ->nullable()
                ->constrained('stores')
                ->onUpdate('cascade')
                ->nullOnDelete();
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wish_lists');
    }
};
