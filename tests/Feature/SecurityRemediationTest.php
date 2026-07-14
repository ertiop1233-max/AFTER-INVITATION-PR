<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyUploadNonce;
use App\Models\Admin;
use App\Models\Client;
use App\Models\DriveCleanupJob;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use App\Providers\AppServiceProvider;
use App\Services\CleanupService;
use App\Services\ClientPasswordService;
use App\Services\EventService;
use App\Services\UploadService;
use DomainException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    public function test_partial_event_update_preserves_omitted_options(): void
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
        $this->assertTrue($event->allow_photos);
        $this->assertTrue($event->allow_videos);
        $this->assertTrue($event->allow_voice);
        $this->assertTrue($event->allow_messages);
    }

    public function test_event_form_can_explicitly_disable_an_option(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'form-admin@example.com',
            'password' => Hash::make('Password123!'),
        ]);
        [$event] = $this->createEventAndSubmission('form-editable', 'form-editable-token');

        $this->withSession([
            'admin_id' => $admin->id,
            'admin_login_at' => now(),
        ])->put(route('admin.events.update', $event), [
            'title' => $event->title,
            'event_type' => $event->event_type,
            'allow_photos' => '0',
            'allow_videos' => '1',
            'allow_voice' => '1',
            'allow_messages' => '1',
        ])->assertRedirect(route('admin.events.show', $event));

        $event->refresh();
        $this->assertFalse($event->allow_photos);
        $this->assertTrue($event->allow_videos);
        $this->assertTrue($event->allow_voice);
        $this->assertTrue($event->allow_messages);
    }

    public function test_client_event_id_is_cast_to_an_integer(): void
    {
        [$event] = $this->createEventAndSubmission('typed-client', 'typed-client-token');
        $client = Client::create([
            'event_id' => (string) $event->id,
            'name' => 'Typed Client',
            'email' => 'typed-client@example.com',
            'password_encrypted' => app(ClientPasswordService::class)->encrypt('Password123!'),
        ]);

        $this->assertIsInt($client->fresh()->event_id);
        $this->assertSame($event->id, $client->fresh()->event_id);
    }

    public function test_login_limiter_has_an_ip_only_ceiling_across_distinct_emails(): void
    {
        (new AppServiceProvider($this->app))->boot();
        $server = ['REMOTE_ADDR' => '198.51.100.42'];

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $response = $this->withServerVariables($server)
                ->withSession(['_token' => 'test-token'])
                ->post('/admin/login', [
                    '_token' => 'test-token',
                    'email' => "attacker{$attempt}@example.com",
                    'password' => 'WrongPassword123!',
                ]);

            $this->assertNotSame(429, $response->getStatusCode());
        }

        $this->withServerVariables($server)
            ->withSession(['_token' => 'test-token'])
            ->post('/admin/login', [
                '_token' => 'test-token',
                'email' => 'attacker31@example.com',
                'password' => 'WrongPassword123!',
            ])
            ->assertTooManyRequests();
    }

    public function test_reenqueuing_cleanup_resets_a_failed_job_for_retry(): void
    {
        $this->mockStorageProvider();
        $job = DriveCleanupJob::create([
            'drive_resource_id' => 'retry-file',
            'resource_type' => DriveCleanupJob::RESOURCE_FILE,
            'status' => DriveCleanupJob::STATUS_FAILED,
            'attempts' => 10,
            'next_retry_at' => now()->addDay(),
            'last_error' => 'Previous deletion failed',
        ]);

        app(CleanupService::class)->enqueueFileDeletion('retry-file');

        $job->refresh();
        $this->assertSame(DriveCleanupJob::STATUS_PENDING, $job->status);
        $this->assertSame(0, $job->attempts);
        $this->assertNull($job->next_retry_at);
        $this->assertNull($job->last_error);
        $this->assertSame(1, DriveCleanupJob::where('drive_resource_id', 'retry-file')->count());
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

    public function test_client_streams_reject_resources_that_are_not_ready(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('readiness', 'readiness-token');
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
            'storage_id' => 'partial-file',
            'thumbnail_storage_id' => 'partial-thumbnail',
            'status' => Media::STATUS_UPLOADING,
        ]);
        $submission->update([
            'voice_storage_id' => 'draft-voice',
            'voice_storage_path' => 'voice/draft.webm',
        ]);

        $this->withSession($this->clientSession($event))
            ->get(route('client.media.view', $media))
            ->assertNotFound();
        $this->get(route('client.media.thumbnail', $media))->assertNotFound();
        $this->get(route('client.submissions.voice', $submission))->assertNotFound();
    }

    public function test_ready_client_streams_use_a_short_private_cache(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('cache', 'cache-token');
        $submission->update([
            'status' => Submission::STATUS_COMPLETED,
            'submitted_at' => now(),
            'voice_storage_id' => 'voice-file',
            'voice_storage_path' => 'voice/recording.webm',
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
            'storage_id' => 'ready-file',
            'thumbnail_storage_id' => 'ready-thumbnail',
            'status' => Media::STATUS_UPLOADED,
            'uploaded_at' => now(),
        ]);

        $provider = $this->mockStorageProvider();
        $provider->shouldReceive('getFileStream')
            ->times(3)
            ->andReturnUsing(fn () => Utils::streamFor('ready'));

        $this->withSession($this->clientSession($event));
        $responses = [
            $this->get(route('client.media.view', $media)),
            $this->get(route('client.media.thumbnail', $media)),
            $this->get(route('client.submissions.voice', $submission)),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $response->assertHeader('Cache-Control', 'max-age=300, must-revalidate, private');
        }
    }

    public function test_failed_chunk_completion_persists_failed_state_after_transaction_rollback(): void
    {
        [$event, $submission] = $this->createEventAndSubmission('rollback', 'rollback-token');
        $media = Media::create([
            'submission_id' => $submission->id,
            'event_id' => $event->id,
            'media_type' => Media::TYPE_PHOTO,
            'original_filename' => 'photo.jpg',
            'stored_filename' => 'stored.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'file_size_bytes' => 5,
            'storage_path' => 'path',
            'resumable_uri' => 'https://upload.example.test/session',
            'status' => Media::STATUS_UPLOADING,
        ]);

        $provider = $this->mockStorageProvider();
        $provider->shouldReceive('uploadChunk')->once()->andReturn([
            'completed' => true,
            'file_id' => 'wrong-sized-file',
            'size' => 4,
        ]);

        try {
            app(UploadService::class)->processChunk($media, "\xFF\xD8\xFF\xE0\x00", 0, 5);
            $this->fail('A size mismatch should fail upload completion.');
        } catch (DomainException $e) {
            $this->assertSame('File size mismatch.', $e->getMessage());
        }

        $media->refresh();
        $this->assertSame(Media::STATUS_FAILED, $media->status);
        $this->assertNull($media->resumable_uri);
        $this->assertNull($media->storage_id);
        $this->assertFalse($media->isUploaded());
    }

    public function test_hardening_migration_reports_duplicate_cleanup_jobs_before_schema_changes(): void
    {
        $migration = require database_path('migrations/2026_07_14_000008_harden_sessions_uploads_and_cleanup.php');
        $migration->down();

        DB::table('drive_cleanup_jobs')->insert([
            [
                'drive_resource_id' => 'duplicate-file',
                'resource_type' => 'file',
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'drive_resource_id' => 'duplicate-file',
                'resource_type' => 'file',
                'status' => 'failed',
                'attempts' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $message = null;

        try {
            $migration->up();
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
        } finally {
            DB::table('drive_cleanup_jobs')->where('drive_resource_id', 'duplicate-file')->delete();

            if (! Schema::hasColumn('clients', 'auth_version')) {
                $migration->up();
            }
        }

        $this->assertNotNull($message);
        $this->assertStringContainsString('drive_resource_id=duplicate-file', $message);
        $this->assertStringContainsString('resource_type=file', $message);
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

    private function clientSession(Event $event): array
    {
        $client = Client::create([
            'event_id' => $event->id,
            'name' => 'Client',
            'email' => $event->upload_slug.'@example.com',
            'password_encrypted' => app(ClientPasswordService::class)->encrypt('Password123!'),
        ]);

        return [
            'client_id' => $client->id,
            'event_id' => $event->id,
            'client_auth_version' => $client->auth_version,
            'client_login_at' => now(),
        ];
    }
}
