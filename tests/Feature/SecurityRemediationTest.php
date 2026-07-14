<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyUploadNonce;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use App\Services\ClientPasswordService;
use App\Services\EventService;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_credentials_cannot_access_another_events_submission(): void
    {
        [$firstEvent] = $this->createEventAndSubmission('first', 'first-token');
        [, $otherSubmission] = $this->createEventAndSubmission('other', 'other-token');

        $response = $this->postJson('/api/submissions/finalize', [
            'submission_id' => $otherSubmission->id,
        ], $this->uploadHeaders($firstEvent));

        $response->assertNotFound();
        $this->assertTrue($otherSubmission->fresh()->isDraft());
    }

    public function test_expired_upload_nonce_is_rejected(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('expired', 'expired-token');
        $nonce = VerifyUploadNonce::generateNonce($event->upload_token, now()->subSecond()->timestamp);

        $this->postJson('/api/submissions/finalize', [
            'submission_id' => $submission->id,
        ], [
            'X-Upload-Nonce' => $nonce,
            'X-Upload-Token' => $event->upload_token,
        ])->assertForbidden();
    }

    public function test_closed_event_rejects_existing_draft_uploads(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('closed', 'closed-token');
        $event->update(['status' => Event::STATUS_CLOSED]);

        $this->postJson('/api/upload/init', [
            'submission_id' => $submission->id,
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 1024,
        ], $this->uploadHeaders($event))->assertStatus(410);
    }

    public function test_event_update_accepts_actual_form_payload_and_persists_unchecked_options(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        [$event] = $this->createEventAndSubmission('editable', 'editable-token');

        $response = $this->withSession([
            'admin_id' => $admin->id,
            'admin_login_at' => now(),
        ])->put(route('admin.events.update', $event), [
            'title' => 'Updated title',
            'event_type' => 'Wedding',
        ]);

        $response->assertRedirect(route('admin.events.show', $event));
        $event->refresh();
        $this->assertSame('Updated title', $event->title);
        $this->assertFalse($event->allow_photos);
        $this->assertFalse($event->allow_videos);
        $this->assertFalse($event->allow_voice);
        $this->assertFalse($event->allow_messages);
    }

    public function test_password_reset_revokes_existing_client_session(): void
    {
        [$event] = $this->createEventAndSubmission('client', 'client-token');
        $client = Client::create([
            'event_id' => $event->id,
            'name' => 'Client',
            'email' => 'client@example.com',
            'password_encrypted' => app(ClientPasswordService::class)->encrypt('OldPassword123!'),
        ]);

        app(EventService::class)->resetClientPassword($event, 'NewPassword123!');

        $this->withSession([
            'client_id' => $client->id,
            'event_id' => $event->id,
            'client_auth_version' => 1,
            'client_login_at' => now(),
        ])->get(route('client.dashboard'))
            ->assertRedirect(route('client.login'));
    }

    public function test_invalid_file_signature_is_rejected_before_storage_upload(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('signature', 'signature-token');
        $media = Media::create([
            'submission_id' => $submission->id,
            'event_id' => $event->id,
            'media_type' => Media::TYPE_PHOTO,
            'original_filename' => 'photo.jpg',
            'stored_filename' => 'stored.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 4,
            'storage_path' => 'path',
            'resumable_uri' => 'https://upload.example.test/session',
            'status' => Media::STATUS_UPLOADING,
        ]);

        $headers = $this->uploadHeaders($event);
        $response = $this->call(
            'POST',
            "/api/upload/chunk?media_id={$media->id}&offset=0&total_size=4",
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_UPLOAD_NONCE' => $headers['X-Upload-Nonce'],
                'HTTP_X_UPLOAD_TOKEN' => $headers['X-Upload-Token'],
            ],
            'evil'
        );

        $response->assertStatus(409);
        $this->assertSame(Media::STATUS_FAILED, $media->fresh()->status);
    }

    public function test_deleting_completed_media_reconciles_submission_and_event_counters(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('counters', 'counters-token');
        $event->update([
            'total_submissions' => 1,
            'total_photos' => 1,
            'total_storage_bytes' => 1024,
        ]);
        $submission->update([
            'status' => Submission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'total_photos' => 1,
            'total_size_bytes' => 1024,
        ]);
        $media = Media::create([
            'submission_id' => $submission->id,
            'event_id' => $event->id,
            'media_type' => Media::TYPE_PHOTO,
            'original_filename' => 'photo.jpg',
            'stored_filename' => 'stored.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 1024,
            'storage_path' => 'path',
            'storage_id' => 'drive-file',
            'status' => Media::STATUS_UPLOADED,
            'uploaded_at' => now(),
        ]);

        app(UploadService::class)->deleteMedia($media);

        $this->assertSame(0, $submission->fresh()->total_photos);
        $this->assertSame(0, $submission->fresh()->total_size_bytes);
        $this->assertSame(0, $event->fresh()->total_photos);
        $this->assertSame(0, $event->fresh()->total_storage_bytes);
    }

    private function createEventAndSubmission(string $slug, string $token): array
    {
        $event = Event::create([
            'title' => ucfirst($slug).' Event',
            'event_type' => 'Wedding',
            'status' => Event::STATUS_ACTIVE,
            'upload_token' => $token,
            'upload_slug' => $slug,
            'storage_root_folder_id' => "root-{$slug}",
        ]);

        $submission = Submission::create([
            'event_id' => $event->id,
            'upload_session_key' => str_pad($slug, 16, '-'),
            'contributor_name' => 'Test Guest',
            'status' => Submission::STATUS_DRAFT,
            'storage_folder_id' => "folder-{$slug}",
        ]);

        return [$event, $submission];
    }

    private function uploadHeaders(Event $event): array
    {
        return [
            'X-Upload-Nonce' => VerifyUploadNonce::generateNonce($event->upload_token),
            'X-Upload-Token' => $event->upload_token,
        ];
    }
}
