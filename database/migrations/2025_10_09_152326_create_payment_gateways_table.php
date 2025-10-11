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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // stripe, paystack, flutterwave
            $table->string('name'); // Stripe, Paystack, Flutterwave
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('mode')->default('test');
            $table->json('supported_currencies')->nullable();
            $table->json('credential_schema')->nullable();
            $table->json('setting_schema')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['is_default', 'sort_order']);
            $table->index(['disabled_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
