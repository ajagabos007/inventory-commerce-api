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
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->boolean('is_warehouse')->default(0);
            $table->foreignUuid('manager_staff_id')
                ->nullable()
                ->constrained('staff')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->timestamps();
            $table->softDeletes('deleted_at', precision: 0);

            $table->unique(['name', 'address']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignUuid('store_id')
                ->nullable()
                ->constrained('stores')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn('store_id');
        });

        Schema::dropIfExists('stores');
    }
};
