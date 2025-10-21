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
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2); // Percentage or fixed amount
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->decimal('maximum_discount_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable(); // Total usage limit
            $table->integer('usage_limit_per_user')->default(1);
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
