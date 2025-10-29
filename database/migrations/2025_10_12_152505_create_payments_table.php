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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->nullable()
                ->contrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();

            $table->string('currency')->nullable();
            $table->decimal('amount', $total = 19, $places = 2);
            $table->text('description')->nullable();
            $table->foreignUuid('payment_gateway_id')
                ->nullable()
                ->contrained('payment_gateways')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('gateway_reference')->nullable(); // reference returned by the gateway
            $table->string('transaction_reference')->nullable();
            $table->string('transaction_status')->nullable();

            $table->string('status')->nullable();
            $table->string('method')->nullable(); // e.g. card, bank_transfer, ussd

            $table->ipAddress('ip_address')->nullable();
            $table->text('callback_url')->nullable();
            $table->text('cancel_url')->nullable();
            $table->text('checkout_url')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verifier_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
