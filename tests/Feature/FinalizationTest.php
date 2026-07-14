<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalizationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStorageProviderForFolderCreation();

        $this->event = Event::create([
            'title' => 'Test Event',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'test-token-fnlz',
            'upload_slug' => 'test-slug-fnlz',
            'storage_root_folder_id' => 'fake-root-folder-id',
        ]);

        $this->submission = Submission::create([
            'event_id' => $this->event->id,
            'upload_session_key' => 'test-session-fnlz',
            'contributor_name' => 'Test Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'fake-folder-id',
        ]);
    }

    public function test_finalization_includes_only_uploaded_media(): void
    {
        Media::create([
            'submission_id' => $this->submission->id,
            'event_id' => $this->event->id,
            'media_type' => 'photo',
            'original_filename' => 'photo1.jpg',
            'stored_filename' => 'uuid1.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 1024,
            'storage_path' => 'path1',
            'storage_id' => 'file-id-1',
            'status' => Media::STATUS_UPLOADED,
            'uploaded_at' => now(),
        ]);

        Media::create([
            'submission_id' => $this->submission->id,
            'event_id' => $this->event->id,
            'media_type' => 'photo',
            'original_filename' => 'photo2.jpg',
            'stored_filename' => 'uuid2.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 2048,
            'storage_path' => 'path2',
            'status' => Media::STATUS_UPLOADING,
        ]);

        Media::create([
            'submission_id' => $this->submission->id,
            'event_id' => $this->event->id,
            'media_type' => 'video',
            'original_filename' => 'video1.mp4',
            'stored_filename' => 'uuid3.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'file_size_bytes' => 4096,
            'storage_path' => 'path3',
            'status' => Media::STATUS_FAILED,
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('test-token-fnlz');

        $response = $this->postJson('/api/submissions/finalize', [
            'submission_id' => $this->submission->id,
            'written_message' => 'Test message',
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-token-fnlz',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->submission->refresh();

        $this->assertEquals(Submission::STATUS_COMPLETED, $this->submission->status);
        $this->assertEquals(1, $this->submission->total_photos, 'Only 1 uploaded photo should be counted');
        $this->assertEquals(0, $this->submission->total_videos, 'Failed video should not be counted');
        $this->assertEquals(1024, $this->submission->total_size_bytes, 'Total size should only include uploaded media');
    }

    public function test_finalize_idempotent_on_completed_submission(): void
    {
        $this->submission->update([
            'status' => Submission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'total_photos' => 2,
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('test-token-fnlz');

        $response = $this->postJson('/api/submissions/finalize', [
            'submission_id' => $this->submission->id,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-token-fnlz',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertEquals(2, $this->submission->refresh()->total_photos);
    }
}