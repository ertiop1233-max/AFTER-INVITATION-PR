<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyUploadNonce;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
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

    public function test_finalization_rejects_incomplete_media(): void
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

        $nonce = VerifyUploadNonce::generateNonce('test-token-fnlz');

        $response = $this->postJson('/api/submissions/finalize', [
            'submission_id' => $this->submission->id,
            'written_message' => 'Test message',
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-token-fnlz',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);

        $this->submission->refresh();

        $this->assertEquals(Submission::STATUS_DRAFT, $this->submission->status);
        $this->assertEquals(0, $this->submission->total_photos);
        $this->assertEquals(0, $this->submission->total_videos);
        $this->assertEquals(0, $this->submission->total_size_bytes);
    }

    public function test_finalize_idempotent_on_completed_submission(): void
    {
        $this->submission->update([
            'status' => Submission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'total_photos' => 2,
        ]);

        $nonce = VerifyUploadNonce::generateNonce('test-token-fnlz');

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
