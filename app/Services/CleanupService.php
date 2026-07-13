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
            ->where('created_at', '<', $cutoff)
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
            ->where('created_at', '<', $cutoff)
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
        if ($submission->storage_folder_id) {
            $this->enqueueFolderDeletion($submission->storage_folder_id);
        }

        if ($submission->voice_storage_id) {
            $this->enqueueFileDeletion($submission->voice_storage_id);
        }

        foreach ($submission->media as $media) {
            if ($media->storage_id) {
                $this->enqueueFileDeletion($media->storage_id);
            }
            if ($media->thumbnail_storage_id) {
                $this->enqueueFileDeletion($media->thumbnail_storage_id);
            }
        }

        $submission->delete();

        $this->processPendingJobs();
    }

    public function enqueueFolderDeletion(string $folderId): void
    {
        $this->enqueueDeletion($folderId, DriveCleanupJob::RESOURCE_FOLDER);
    }

    public function enqueueFileDeletion(string $fileId): void
    {
        $this->enqueueDeletion($fileId, DriveCleanupJob::RESOURCE_FILE);
    }

    public function processPendingJobs(): void
    {
        $jobs = DriveCleanupJob::where('status', DriveCleanupJob::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(50)
            ->get();

        foreach ($jobs as $job) {
            $this->processJob($job);
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
        $existing = DriveCleanupJob::where('drive_resource_id', $resourceId)
            ->where('status', DriveCleanupJob::STATUS_PENDING)
            ->first();

        if ($existing) {
            return;
        }

        DriveCleanupJob::create([
            'drive_resource_id' => $resourceId,
            'resource_type' => $resourceType,
            'status' => DriveCleanupJob::STATUS_PENDING,
        ]);
    }
}
