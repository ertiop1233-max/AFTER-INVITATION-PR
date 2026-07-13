<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('upload_session_key', 64)->unique();
            $table->string('contributor_name');
            $table->text('written_message')->nullable();
            $table->string('voice_storage_id')->nullable();
            $table->string('voice_storage_path')->nullable();
            $table->unsignedInteger('voice_duration_seconds')->nullable();
            $table->unsignedBigInteger('voice_size_bytes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('storage_folder_id')->nullable();
            $table->unsignedInteger('total_photos')->default(0);
            $table->unsignedInteger('total_videos')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'created_at']);
            $table->index('contributor_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
