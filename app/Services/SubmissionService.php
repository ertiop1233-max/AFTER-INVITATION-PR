<?php

namespace App\Services;

use App\Models\DriveCleanupJob;
use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SubmissionService
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly EventService $eventService,
        private readonly CleanupService $cleanupService,
    ) {}

    public function findOrCreateDraft(Event $event, string $uploadSessionKey, string $contributorName): array
    {
        $existing = Submission::where('upload_session_key', $uploadSessionKey)->first();

        if ($existing) {
            if ($existing->isCompleted()) {
                return [
                    'status' => 'completed',
                    'submission_id' => $existing->id,
                ];
            }

            if ($existing->event_id !== $event->id) {
                return [
                    'status' => 'invalid',
                    'submission_id' => null,
                ];
            }

            return [
                'status' => 'draft',
                'submission_id' => $existing->id,
                'storage_folder_id' => $existing->storage_folder_id,
            ];
        }

        return $this->createDraft($event, $uploadSessionKey, $contributorName);
    }

    public function createDraft(Event $event, string $uploadSessionKey, string $contributorName): array
    {
        $folderId = null;
        try {
            $folderId = $this->storageService->createFolder(
                "sub_" . Str::random(12),
                $event->storage_root_folder_id
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create submission folder', ['error' => $e->getMessage()]);
            throw new RuntimeException('Unable to create storage folder for submission');
        }

        try {
            $submission = DB::transaction(function () use ($event, $uploadSessionKey, $contributorName, $folderId) {
                return Submission::create([
                    'event_id' => $event->id,
                    'upload_session_key' => $uploadSessionKey,
                    'contributor_name' => $contributorName,
                    'status' => Submission::STATUS_DRAFT,
                    'storage_folder_id' => $folderId,
                ]);
            });

            return [
                'status' => 'draft',
                'submission_id' => $submission->id,
                'storage_folder_id' => $folderId,
            ];
        } catch (QueryException $e) {
            Log::error('Submission DB creation failed, compensating with Drive cleanup', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            $this->cleanupService->enqueueFolderDeletion($folderId);
            throw $e;
        }
    }

    public function finalize(Submission $submission, ?string $message = null, ?array $voiceData = null): Submission
    {
        if ($submission->isCompleted()) {
            return $submission;
        }

        return DB::transaction(function () use ($submission, $message, $voiceData) {
            $uploadedMedia = $submission->media()
                ->where('status', Media::STATUS_UPLOADED)
                ->lockForUpdate()
                ->get();

            $photoCount = $uploadedMedia->where('media_type', Media::TYPE_PHOTO)->count();
            $videoCount = $uploadedMedia->where('media_type', Media::TYPE_VIDEO)->count();
            $totalSize = (int) $uploadedMedia->sum('file_size_bytes');

            $updateData = [
                'status' => Submission::STATUS_COMPLETED,
                'submitted_at' => now(),
                'total_photos' => $photoCount,
                'total_videos' => $videoCount,
                'total_size_bytes' => $totalSize,
            ];

            if ($message !== null && trim($message) !== '') {
                $updateData['written_message'] = $message;
            }

            if ($voiceData !== null) {
                $updateData = array_merge($updateData, $voiceData);
            }

            $submission->update($updateData);

            $this->eventService->incrementCounters($submission->event, [
                'submissions' => 1,
                'photos' => $photoCount,
                'videos' => $videoCount,
                'voice' => isset($voiceData['voice_storage_id']) ? 1 : 0,
                'messages' => ($message !== null && trim($message) !== '') ? 1 : 0,
                'storage_bytes' => $totalSize + ($voiceData['voice_size_bytes'] ?? 0),
            ]);

            return $submission->fresh();
        });
    }

    public function findSubmissionById(int $submissionId): ?Submission
    {
        return Submission::find($submissionId);
    }

    public function findSubmissionBySessionKey(string $sessionKey): ?Submission
    {
        return Submission::where('upload_session_key', $sessionKey)->first();
    }

    public function deleteSubmission(Submission $submission): void
    {
        $resourceIds = [];

        if ($submission->storage_folder_id) {
            $resourceIds[] = ['id' => $submission->storage_folder_id, 'type' => DriveCleanupJob::RESOURCE_FOLDER];
        }
        if ($submission->voice_storage_id) {
            $resourceIds[] = ['id' => $submission->voice_storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
        }

        foreach ($submission->media as $media) {
            if ($media->storage_id) {
                $resourceIds[] = ['id' => $media->storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
            if ($media->thumbnail_storage_id) {
                $resourceIds[] = ['id' => $media->thumbnail_storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
        }

        DB::transaction(function () use ($submission, $resourceIds) {
            foreach ($resourceIds as $resource) {
                DriveCleanupJob::create([
                    'drive_resource_id' => $resource['id'],
                    'resource_type' => $resource['type'],
                    'status' => DriveCleanupJob::STATUS_PENDING,
                ]);
            }

            $submission->delete();
        });

        $this->cleanupService->processPendingJobs();
    }
}
