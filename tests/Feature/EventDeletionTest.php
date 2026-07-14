<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DriveCleanupJob;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_deletion_deletes_db_and_queues_drive_cleanup(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Delete Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'delete-token',
            'upload_slug' => 'delete-slug',
            'storage_root_folder_id' => 'root-folder-123',
        ]);

        $submission = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'del-session',
            'contributor_name' => 'Del Guest',
            'status' => Submission::STATUS_COMPLETED,
            'storage_folder_id' => 'sub-folder-456',
            'voice_storage_id' => 'voice-file-789',
            'submitted_at' => now(),
        ]);

        Media::create([
            'submission_id' => $submission->id,
            'event_id' => $event->id,
            'media_type' => 'photo',
            'original_filename' => 'del.jpg',
            'stored_filename' => 'uuid.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 1024,
            'storage_path' => 'path',
            'storage_id' => 'media-file-abc',
            'thumbnail_storage_id' => 'thumb-file-xyz',
            'status' => Media::STATUS_UPLOADED,
            'uploaded_at' => now(),
        ]);

        Admin::create(['email' => 'admin@test.com', 'password' => bcrypt('Password123!')]);
        $this->postWithCsrf('/admin/login', ['email' => 'admin@test.com', 'password' => 'Password123!']);

        $response = $this->deleteWithCsrf("/admin/events/{$event->id}", [
            'confirm_title' => 'Delete Test',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('submissions', ['id' => $submission->id]);
        $this->assertDatabaseMissing('media', ['event_id' => $event->id]);

        $this->assertDatabaseHas('drive_cleanup_jobs', [
            'drive_resource_id' => 'root-folder-123',
            'resource_type' => DriveCleanupJob::RESOURCE_FOLDER,
        ]);

        $this->assertDatabaseMissing('drive_cleanup_jobs', [
            'drive_resource_id' => 'sub-folder-456',
        ]);

        $this->assertDatabaseMissing('drive_cleanup_jobs', [
            'drive_resource_id' => 'media-file-abc',
        ]);

        $this->assertDatabaseMissing('drive_cleanup_jobs', [
            'drive_resource_id' => 'voice-file-789',
        ]);
    }

    public function test_event_deletion_requires_type_to_confirm(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Confirm Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'confirm-token',
            'upload_slug' => 'confirm-slug',
            'storage_root_folder_id' => 'root-folder',
        ]);

        Admin::create(['email' => 'admin@test.com', 'password' => bcrypt('Password123!')]);
        $this->postWithCsrf('/admin/login', ['email' => 'admin@test.com', 'password' => 'Password123!']);

        $response = $this->deleteWithCsrf("/admin/events/{$event->id}", [
            'confirm_title' => 'Wrong Title',
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
