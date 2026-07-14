<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Event;
use App\Models\Submission;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStorageProviderForFolderCreation();

        $this->event = Event::create([
            'title' => 'Test Event',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'test-upload-token-123',
            'upload_slug' => 'test-slug-123',
            'storage_root_folder_id' => 'fake-root-folder-id',
        ]);

        $this->withSession([
            'admin_id' => Admin::create(['email' => 'a@b.c', 'password' => bcrypt('Password123!')])->id,
        ]);
    }

    public function test_submission_start_is_idempotent(): void
    {
        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('test-upload-token-123');

        $firstResponse = $this->postJson('/api/submissions/start', [
            'event_token' => 'test-upload-token-123',
            'upload_session_key' => 'session-key-abc',
            'contributor_name' => 'Test Guest',
            'nonce' => $nonce,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-upload-token-123',
        ]);

        $firstResponse->assertOk();
        $firstResponse->assertJsonPath('status', 'draft');
        $firstId = $firstResponse->json('submission_id');

        $secondResponse = $this->postJson('/api/submissions/start', [
            'event_token' => 'test-upload-token-123',
            'upload_session_key' => 'session-key-abc',
            'contributor_name' => 'Test Guest',
            'nonce' => $nonce,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-upload-token-123',
        ]);

        $secondResponse->assertOk();
        $secondResponse->assertJsonPath('status', 'draft');
        $secondId = $secondResponse->json('submission_id');

        $this->assertEquals($firstId, $secondId, 'Idempotent start should return same submission_id');
        $this->assertEquals(1, Submission::count(), 'Only one draft submission should exist');
    }

    public function test_completed_submission_returns_completed_status(): void
    {
        $submission = Submission::create([
            'event_id' => $this->event->id,
            'upload_session_key' => 'completed-session-key',
            'contributor_name' => 'Test Guest',
            'status' => Submission::STATUS_COMPLETED,
            'storage_folder_id' => 'fake-folder-id',
            'submitted_at' => now(),
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('test-upload-token-123');

        $response = $this->postJson('/api/submissions/start', [
            'event_token' => 'test-upload-token-123',
            'upload_session_key' => 'completed-session-key',
            'contributor_name' => 'Test Guest',
            'nonce' => $nonce,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-upload-token-123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('submission_id', $submission->id);
    }

    public function test_refresh_recovery_reuses_draft_submission(): void
    {
        $existing = Submission::create([
            'event_id' => $this->event->id,
            'upload_session_key' => 'refresh-test-key',
            'contributor_name' => 'Refresh Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => 'fake-folder-id',
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('test-upload-token-123');

        $response = $this->postJson('/api/submissions/start', [
            'event_token' => 'test-upload-token-123',
            'upload_session_key' => 'refresh-test-key',
            'contributor_name' => 'Refresh Guest',
            'nonce' => $nonce,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'test-upload-token-123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('submission_id', $existing->id);
        $this->assertEquals(1, Submission::count());
    }
}