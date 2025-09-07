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
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('path');  // folder/files/files1.extension
            $table->text('url')->nullable(); // https://url/folder/files/files1.extension
            $table->string('type')->nullable(); // image, audio, video, document
            $table->string('mime_type')->nullable(); // image/png , application/pdf
            $table->string('extension')->nullable(); // png, jpeg,
            $table->unsignedBigInteger('size')->comment('bytes');
            $table->string('storage')->default('public'); // public, local, s3 etc
            $table->uuidMorphs('attachable');
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
