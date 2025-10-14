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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->nullOnDelete();
            $table->foreignUuid('store_id')
                ->nullable()
                ->constrained('stores')
                ->onUpdate('cascade')
                ->nullOnDelete();
            $table->string('name');
            $table->string('phone_number');
            $table->string('email')->nullable();
            $table->string('delivery_method')->nullable();
            $table->json('delivery_address')->nullable();

            $table->json('pickup_address')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();

            $table->foreignUuid('discount_id')
                ->nullable()
                ->constrained('discounts')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('subtotal_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
