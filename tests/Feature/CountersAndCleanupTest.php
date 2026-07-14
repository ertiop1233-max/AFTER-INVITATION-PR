<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountersAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_counters_update_on_finalize(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Counter Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'counter-token',
            'upload_slug' => 'counter-slug',
            'storage_root_folder_id' => 'fake-root',
            'total_submissions' => 0,
            'total_photos' => 0,
        ]);

        $submission = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'counter-session',
            'contributor_name' => 'Counter Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'fake-folder',
        ]);

        Media::create([
            'submission_id' => $submission->id,
            'event_id' => $event->id,
            'media_type' => 'photo',
            'original_filename' => 'test.jpg',
            'stored_filename' => 'uuid.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 5120,
            'storage_path' => 'path',
            'storage_id' => 'file-id',
            'status' => Media::STATUS_UPLOADED,
            'uploaded_at' => now(),
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('counter-token');

        $this->postJson('/api/submissions/finalize', [
            'submission_id' => $submission->id,
            'written_message' => 'Hello',
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'counter-token',
        ])->assertOk();

        $event->refresh();

        $this->assertEquals(1, $event->total_submissions);
        $this->assertEquals(1, $event->total_photos);
        $this->assertEquals(5120, $event->total_storage_bytes);
        $this->assertEquals(1, $event->total_messages);
    }

    public function test_cleanup_removes_stale_drafts(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Cleanup Test',
            'event_type' => 'Party',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'cleanup-token',
            'upload_slug' => 'cleanup-slug',
            'storage_root_folder_id' => 'fake-root',
        ]);

        $staleDraft = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'stale-key',
            'contributor_name' => 'Stale Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'stale-folder-id',
        ]);

        Submission::where('id', $staleDraft->id)->update(['created_at' => now()->subDays(2)]);

        $freshDraft = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'fresh-key',
            'contributor_name' => 'Fresh Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'fresh-folder-id',
        ]);

        $this->artisan('memoryvault:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('submissions', ['id' => $staleDraft->id]);
        $this->assertDatabaseHas('submissions', ['id' => $freshDraft->id]);
    }

    public function test_cleanup_does_not_remove_completed_submissions(): void
    {
        $this->mockStorageProviderForFolderCreation();

        $event = Event::create([
            'title' => 'Keep Test',
            'event_type' => 'Party',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'keep-token',
            'upload_slug' => 'keep-slug',
            'storage_root_folder_id' => 'fake-root',
        ]);

        $oldCompleted = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => 'old-completed-key',
            'contributor_name' => 'Old Completed',
            'status' => Submission::STATUS_COMPLETED,
            'storage_folder_id' => 'old-completed-folder',
            'submitted_at' => now(),
        ]);

        Submission::where('id', $oldCompleted->id)->update(['created_at' => now()->subDays(3)]);

        $this->artisan('memoryvault:cleanup')->assertSuccessful();

        $this->assertDatabaseHas('submissions', ['id' => $oldCompleted->id]);
    }
}