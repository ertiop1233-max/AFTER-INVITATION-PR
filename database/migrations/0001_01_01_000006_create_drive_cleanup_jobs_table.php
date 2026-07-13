<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_cleanup_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('drive_resource_id');
            $table->string('resource_type', 10);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index('drive_resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_cleanup_jobs');
    }
};
