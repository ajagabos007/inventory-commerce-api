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
        Schema::create('model_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('viewable');
            // Auth user
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            // Guests
            $table->string('session_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Prevent duplicates within X minutes
            $table->timestamp('viewed_at')->useCurrent();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_view_stats');
        Schema::dropIfExists('model_views');
    }
};
