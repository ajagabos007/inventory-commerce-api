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
//        Schema::create('wish_lists', function (Blueprint $table) {
//            $table->uuid('id')->primary();
//            $table->uuidMorphs('item');
//            $table->string('item_name');
//            $table->string('image_image_url')->nullable();
//            $table->decimal('item_price', 8, 2)->nullable();
//            $table->string('session_token')->nullable()->index();
//            $table->uuid('user_id')->nullable()->index();
//            $table->json('options')->nullable();
//            $table->timestamps();
//
//            $table->unique(['user_id', 'item_id', 'item_type']);
//            $table->unique(['session_token', 'item_id', 'item_type']);
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wish_lists');
    }
};
