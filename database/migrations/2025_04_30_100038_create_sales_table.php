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
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->text('barcode')->nullable()->unique();
            $table->foreignUuid('cashier_staff_id')
                ->nullable()
                ->constrained('staff')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Polymorphic buyer (can be users, customers, etc.)
            $table->nullableUuidMorphs('buyerable');

            // Channel: POS or Ecommerce
            $table->string('channel')->default('pos');

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
        Schema::dropIfExists('sales');
    }
};
