<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type', 50);
            $table->string('status', 20)->default('draft');
            $table->timestamp('upload_deadline')->nullable();
            $table->unsignedBigInteger('max_submission_size_bytes')->default(2147483648);
            $table->boolean('allow_photos')->default(true);
            $table->boolean('allow_videos')->default(true);
            $table->boolean('allow_voice')->default(true);
            $table->boolean('allow_messages')->default(true);
            $table->string('upload_token', 64)->unique();
            $table->string('upload_slug')->unique();
            $table->string('storage_root_folder_id')->nullable();
            $table->unsignedInteger('total_submissions')->default(0);
            $table->unsignedInteger('total_photos')->default(0);
            $table->unsignedInteger('total_videos')->default(0);
            $table->unsignedInteger('total_voice_recordings')->default(0);
            $table->unsignedInteger('total_messages')->default(0);
            $table->unsignedBigInteger('total_storage_bytes')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
