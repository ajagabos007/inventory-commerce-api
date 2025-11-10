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
        Schema::create('payment_gateway_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_gateway_id')
                ->constrained('payment_gateways')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('mode'); // test, live
            $table->json('credentials');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['payment_gateway_id', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
    }
};
