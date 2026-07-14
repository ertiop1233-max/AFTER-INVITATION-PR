<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Submission;
use App\Services\Storage\StorageProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_info_response_does_not_expose_internal_ids(): void
    {
        $this->mockStorageProvider();

        $event = Event::create([
            'title' => 'API Hygiene Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'hygiene-token',
            'upload_slug' => 'hygiene-slug',
            'storage_root_folder_id' => 'internal-folder-id-123',
        ]);

        $response = $this->getJson('/api/events/hygiene-token');

        $response->assertOk();
        $response->assertJsonPath('title', 'API Hygiene Test');
        $response->assertJsonPath('status', 'active');

        $content = $response->getContent();
        $this->assertStringNotContainsString('internal-folder-id-123', $content, 'Response must not expose storage_root_folder_id');
        $this->assertStringNotContainsString('storage_path', $content, 'Response must not expose storage paths');
        $this->assertStringNotContainsString('storage_id', $content, 'Response must not expose storage IDs');
    }

    public function test_submission_start_response_does_not_expose_storage_folder_id(): void
    {
        $this->mockStorageProviderForFolderCreation();

        Event::create([
            'title' => 'Start Hygiene',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'start-hygiene-token',
            'upload_slug' => 'start-hygiene-slug',
            'storage_root_folder_id' => 'root-123',
        ]);

        $nonce = \App\Http\Middleware\VerifyUploadNonce::generateNonce('start-hygiene-token');

        $response = $this->postJson('/api/submissions/start', [
            'event_token' => 'start-hygiene-token',
            'upload_session_key' => 'session-hygiene',
            'contributor_name' => 'Hygiene Guest',
            'nonce' => $nonce,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => 'start-hygiene-token',
        ]);

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringNotContainsString('storage_folder_id', $content, 'Response must not expose storage_folder_id');
        $this->assertStringNotContainsString('fake-folder', $content, 'Response must not expose folder IDs');
    }

    public function test_api_endpoints_reject_requests_without_nonce(): void
    {
        $this->mockStorageProvider();

        Event::create([
            'title' => 'Nonce Test',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => 'nonce-token',
            'upload_slug' => 'nonce-slug',
            'storage_root_folder_id' => 'root',
        ]);

        $this->postJson('/api/submissions/start', [
            'event_token' => 'nonce-token',
            'upload_session_key' => 'key',
            'contributor_name' => 'Guest',
            'nonce' => 'nonce',
        ])->assertStatus(403);
    }
}