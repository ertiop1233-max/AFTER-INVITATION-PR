<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DriveCleanupJob;
use App\Models\Event;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EventService
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly CleanupService $cleanupService,
        private readonly QrService $qrService,
        private readonly ClientPasswordService $clientPasswordService,
    ) {}

    public function createEvent(array $data): Event
    {
        $uploadToken = Event::generateUploadToken();
        $uploadSlug = Event::generateUploadSlug($data['title']);

        $rootFolderId = null;
        try {
            $rootFolderId = $this->storageService->createFolder("evt_{$uploadSlug}");
        } catch (\Throwable $e) {
            Log::error('Failed to create event root folder', ['error' => $e->getMessage()]);
            throw new RuntimeException('Unable to create storage folder for event');
        }

        try {
            return DB::transaction(function () use ($data, $uploadToken, $uploadSlug, $rootFolderId) {
                $event = Event::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'event_type' => $data['event_type'],
                    'status' => Event::STATUS_ACTIVE,
                    'upload_deadline' => $data['upload_deadline'] ?? null,
                    'max_submission_size_bytes' => $data['max_submission_size_bytes'] ?? 2147483648,
                    'allow_photos' => $data['allow_photos'] ?? true,
                    'allow_videos' => $data['allow_videos'] ?? true,
                    'allow_voice' => $data['allow_voice'] ?? true,
                    'allow_messages' => $data['allow_messages'] ?? true,
                    'upload_token' => $uploadToken,
                    'upload_slug' => $uploadSlug,
                    'storage_root_folder_id' => $rootFolderId,
                ]);

                Client::create([
                    'event_id' => $event->id,
                    'name' => $data['client_name'],
                    'email' => $data['client_email'],
                    'password_encrypted' => $this->clientPasswordService->encrypt($data['client_password']),
                ]);

                return $event;
            });
        } catch (QueryException $e) {
            Log::error('Event DB creation failed, compensating with Drive cleanup', [
                'folder_id' => $rootFolderId,
                'error' => $e->getMessage(),
            ]);
            $this->cleanupService->enqueueFolderDeletion($rootFolderId);
            throw $e;
        }
    }

    public function generateQrCode(Event $event): ?string
    {
        $url = route('upload.page', ['slug' => $event->upload_slug, 'token' => $event->upload_token]);

        if (!$event->storage_root_folder_id) {
            return null;
        }

        return $this->qrService->generateAndStore($url, $event->storage_root_folder_id);
    }

    public function hasQrCode(Event $event): bool
    {
        if (!$event->storage_root_folder_id) {
            return false;
        }

        return $this->qrService->findQrFile($event->storage_root_folder_id) !== null;
    }

    public function getQrCodeStream(Event $event): mixed
    {
        if (!$event->storage_root_folder_id) {
            return null;
        }

        return $this->qrService->getQrStream($event->storage_root_folder_id);
    }

    public function closeEvent(Event $event): Event
    {
        $event->update(['status' => Event::STATUS_CLOSED]);
        return $event->fresh();
    }

    public function reopenEvent(Event $event): Event
    {
        $event->update(['status' => Event::STATUS_ACTIVE]);
        return $event->fresh();
    }

    public function updateEvent(Event $event, array $data): Event
    {
        $event->update(collect($data)->only([
            'title', 'description', 'event_type', 'upload_deadline',
            'max_submission_size_bytes', 'allow_photos', 'allow_videos',
            'allow_voice', 'allow_messages',
        ])->toArray());

        return $event->fresh();
    }

    public function deleteEvent(Event $event): void
    {
        $event->load(['submissions.media', 'media']);

        $resources = [];

        if ($event->storage_root_folder_id) {
            $resources[] = ['id' => $event->storage_root_folder_id, 'type' => DriveCleanupJob::RESOURCE_FOLDER];
        }

        foreach ($event->submissions as $submission) {
            $resources = array_merge($resources, $this->cleanupService->collectSubmissionResourceIds($submission));
        }

        foreach ($event->media as $media) {
            if ($media->storage_id) {
                $resources[] = ['id' => $media->storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
            if ($media->thumbnail_storage_id) {
                $resources[] = ['id' => $media->thumbnail_storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
        }

        DB::transaction(function () use ($event, $resources) {
            $this->cleanupService->enqueueDeletions($resources);
            $event->delete();
        });

        $this->cleanupService->processPendingJobs();
    }

    public function resetClientPassword(Event $event, string $newPassword): void
    {
        $client = $event->client;
        $client->update([
            'password_encrypted' => $this->clientPasswordService->encrypt($newPassword),
        ]);
    }

    public function incrementCounters(Event $event, array $counts): void
    {
        DB::transaction(function () use ($event, $counts) {
            $event = Event::lockForUpdate()->find($event->id);

            $updates = [];
            if (isset($counts['submissions'])) {
                $updates['total_submissions'] = $event->total_submissions + $counts['submissions'];
            }
            if (isset($counts['photos'])) {
                $updates['total_photos'] = $event->total_photos + $counts['photos'];
            }
            if (isset($counts['videos'])) {
                $updates['total_videos'] = $event->total_videos + $counts['videos'];
            }
            if (isset($counts['voice'])) {
                $updates['total_voice_recordings'] = $event->total_voice_recordings + $counts['voice'];
            }
            if (isset($counts['messages'])) {
                $updates['total_messages'] = $event->total_messages + $counts['messages'];
            }
            if (isset($counts['storage_bytes'])) {
                $updates['total_storage_bytes'] = $event->total_storage_bytes + $counts['storage_bytes'];
            }

            $event->update($updates);
        });
    }
}
