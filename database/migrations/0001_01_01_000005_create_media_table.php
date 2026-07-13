<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 10);
            $table->string('original_filename', 500);
            $table->string('stored_filename');
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('storage_id')->nullable();
            $table->string('storage_path');
            $table->string('thumbnail_storage_id')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('status', 20)->default('uploading');
            $table->timestamp('uploaded_at')->nullable();
            $table->string('resumable_uri', 2048)->nullable();
            $table->timestamps();

            $table->index(['event_id', 'media_type']);
            $table->index(['submission_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
