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
        Schema::create('verification_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('verification_tokenable_id');
            $table->string('verification_tokenable_type');
            $table->string('verification_type')->nullable();
            $table->string('token');
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();

            $table->index(['verification_tokenable_id', 'verification_tokenable_type'], $key = 'verification_tokenable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_tokens');
    }
};
