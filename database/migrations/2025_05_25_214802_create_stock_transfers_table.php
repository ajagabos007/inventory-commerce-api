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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_no')->nullable()->unique();
            $table->foreignUuid('sender_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->text('comment')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone_number')->nullable();
            $table->foreignUuid('from_store_id')
                ->nullable()
                ->constrained('stores')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('to_store_id')
                ->constrained('stores')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignUuid('receiver_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('status')->default('new');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
