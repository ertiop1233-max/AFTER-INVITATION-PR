<?php

namespace App\Services;

use App\Models\DriveCleanupJob;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;

class CleanupService
{
    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function cleanupOrphanedUploads(): void
    {
        $this->cleanupStaleDrafts();
        $this->cleanupStaleMediaOnCompletedSubmissions();
        $this->processPendingJobs();
    }

    public function cleanupStaleDrafts(): void
    {
        $maxAgeHours = config('memoryvault.cleanup.draft_max_age_hours', 24);
        $cutoff = now()->subHours($maxAgeHours);

        $staleDrafts = Submission::where('status', Submission::STATUS_DRAFT)
            ->where('updated_at', '<', $cutoff)
            ->limit(100)
            ->get();

        foreach ($staleDrafts as $submission) {
            $this->cleanupDraftSubmission($submission);
        }
    }

    public function cleanupStaleMediaOnCompletedSubmissions(): void
    {
        $maxAgeHours = config('memoryvault.cleanup.media_max_age_hours', 24);
        $cutoff = now()->subHours($maxAgeHours);

        $staleMedia = Media::whereIn('status', [Media::STATUS_UPLOADING, Media::STATUS_FAILED])
            ->where('updated_at', '<', $cutoff)
            ->whereHas('submission', function ($query) {
                $query->where('status', Submission::STATUS_COMPLETED);
            })
            ->limit(100)
            ->get();

        foreach ($staleMedia as $media) {
            if ($media->storage_id) {
                $this->enqueueFileDeletion($media->storage_id);
            }
            if ($media->thumbnail_storage_id) {
                $this->enqueueFileDeletion($media->thumbnail_storage_id);
            }
            $media->delete();
        }
    }

    public function cleanupDraftSubmission(Submission $submission): void
    {
        $resources = $this->collectSubmissionResourceIds($submission);

        foreach ($resources as $resource) {
            if ($resource['type'] === DriveCleanupJob::RESOURCE_FOLDER) {
                $this->enqueueFolderDeletion($resource['id']);
            } else {
                $this->enqueueFileDeletion($resource['id']);
            }
        }

        $submission->delete();

    }

    public function enqueueFolderDeletion(string $folderId): void
    {
        $this->enqueueDeletion($folderId, DriveCleanupJob::RESOURCE_FOLDER);
    }

    public function enqueueFileDeletion(string $fileId): void
    {
        $this->enqueueDeletion($fileId, DriveCleanupJob::RESOURCE_FILE);
    }

    public function enqueueDeletions(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->enqueueDeletion($resource['id'], $resource['type']);
        }
    }

    public function collectSubmissionResourceIds(Submission $submission): array
    {
        $resources = [];

        if ($submission->storage_folder_id) {
            $resources[] = ['id' => $submission->storage_folder_id, 'type' => DriveCleanupJob::RESOURCE_FOLDER];
        } elseif ($submission->voice_storage_id) {
            $resources[] = ['id' => $submission->voice_storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
        }

        foreach ($submission->media as $media) {
            if (! $submission->storage_folder_id && $media->storage_id) {
                $resources[] = ['id' => $media->storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
            if ($media->thumbnail_storage_id) {
                $resources[] = ['id' => $media->thumbnail_storage_id, 'type' => DriveCleanupJob::RESOURCE_FILE];
            }
        }

        return $resources;
    }

    public function processPendingJobs(): void
    {
        DriveCleanupJob::where('status', DriveCleanupJob::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => DriveCleanupJob::STATUS_PENDING]);

        $jobIds = DriveCleanupJob::where('status', DriveCleanupJob::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(50)
            ->pluck('id');

        foreach ($jobIds as $jobId) {
            $claimed = DriveCleanupJob::whereKey($jobId)
                ->where('status', DriveCleanupJob::STATUS_PENDING)
                ->update(['status' => DriveCleanupJob::STATUS_PROCESSING]);

            if ($claimed === 1) {
                $this->processJob(DriveCleanupJob::findOrFail($jobId));
            }
        }
    }

    public function processJob(DriveCleanupJob $job): void
    {
        $job->increment('attempts');

        try {
            if ($job->resource_type === DriveCleanupJob::RESOURCE_FOLDER) {
                $this->storageService->deleteFolder($job->drive_resource_id);
            } else {
                $this->storageService->deleteFile($job->drive_resource_id);
            }

            $job->update(['status' => DriveCleanupJob::STATUS_COMPLETED]);
        } catch (\Throwable $e) {
            $maxAttempts = config('memoryvault.cleanup.max_retry_attempts', 10);

            if ($job->attempts >= $maxAttempts) {
                $job->update([
                    'status' => DriveCleanupJob::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                ]);
                Log::error('Drive cleanup job permanently failed', [
                    'job_id' => $job->id,
                    'resource_id' => $job->drive_resource_id,
                    'attempts' => $job->attempts,
                    'error' => $e->getMessage(),
                ]);
            } else {
                $delay = config('memoryvault.cleanup.retry_base_delay_seconds', 300);
                $backoffDelay = $delay * pow(2, $job->attempts - 1);

                $job->update([
                    'status' => DriveCleanupJob::STATUS_PENDING,
                    'next_retry_at' => now()->addSeconds($backoffDelay),
                    'last_error' => $e->getMessage(),
                ]);

                Log::warning('Drive cleanup job will retry', [
                    'job_id' => $job->id,
                    'resource_id' => $job->drive_resource_id,
                    'attempt' => $job->attempts,
                    'next_retry' => $job->next_retry_at,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function enqueueDeletion(string $resourceId, string $resourceType): void
    {
        DriveCleanupJob::firstOrCreate([
            'drive_resource_id' => $resourceId,
            'resource_type' => $resourceType,
        ], [
            'status' => DriveCleanupJob::STATUS_PENDING,
        ]);
    }
}
