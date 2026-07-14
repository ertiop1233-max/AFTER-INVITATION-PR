<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('auth_version')->default(1);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->index(['status', 'updated_at'], 'media_status_updated_at_index');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'submissions_status_updated_at_index');
        });

        Schema::table('drive_cleanup_jobs', function (Blueprint $table) {
            $table->unique(
                ['drive_resource_id', 'resource_type'],
                'drive_cleanup_resource_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('drive_cleanup_jobs', function (Blueprint $table) {
            $table->dropUnique('drive_cleanup_resource_unique');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_status_updated_at_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_status_updated_at_index');
            $table->dropColumn('uploaded_bytes');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('auth_version');
        });
    }
};
