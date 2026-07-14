<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
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
            if ($existing->event_id !== $event->id) {
                return [
                    'status' => 'invalid',
                    'submission_id' => null,
                ];
            }

            if ($existing->isCompleted()) {
                return [
                    'status' => 'completed',
                    'submission_id' => $existing->id,
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
                'sub_'.Str::random(12),
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
        } catch (\Throwable $e) {
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
        return DB::transaction(function () use ($submission, $message, $voiceData) {
            $lockedSubmission = Submission::with('event')->lockForUpdate()->findOrFail($submission->id);

            if ($lockedSubmission->isCompleted()) {
                return $lockedSubmission;
            }

            if ($lockedSubmission->event->isClosed() || $lockedSubmission->event->isUploadDeadlinePassed()) {
                throw new \DomainException('This event is no longer accepting submissions.');
            }

            $allMedia = $lockedSubmission->media()
                ->lockForUpdate()
                ->get();

            if ($allMedia->contains(fn (Media $media) => ! $media->isUploaded())) {
                throw new \DomainException('All media uploads must finish before finalizing.');
            }

            $normalizedMessage = $message !== null ? trim($message) : '';
            if ($normalizedMessage !== '' && ! $lockedSubmission->event->allow_messages) {
                throw new \DomainException('Written messages are not allowed for this event.');
            }

            $voiceStorageId = $voiceData['voice_storage_id'] ?? $lockedSubmission->voice_storage_id;
            $voiceSize = (int) ($voiceData['voice_size_bytes'] ?? $lockedSubmission->voice_size_bytes ?? 0);
            if ($voiceStorageId && ! $lockedSubmission->event->allow_voice) {
                throw new \DomainException('Voice messages are not allowed for this event.');
            }

            $photoCount = $allMedia->where('media_type', Media::TYPE_PHOTO)->count();
            $videoCount = $allMedia->where('media_type', Media::TYPE_VIDEO)->count();
            $totalSize = (int) $allMedia->sum('file_size_bytes');

            $updateData = [
                'status' => Submission::STATUS_COMPLETED,
                'submitted_at' => now(),
                'total_photos' => $photoCount,
                'total_videos' => $videoCount,
                'total_size_bytes' => $totalSize,
            ];

            if ($normalizedMessage !== '') {
                $updateData['written_message'] = $normalizedMessage;
            }

            if ($voiceData !== null) {
                $updateData = array_merge($updateData, $voiceData);
            }

            $lockedSubmission->update($updateData);

            $this->eventService->incrementCounters($lockedSubmission->event, [
                'submissions' => 1,
                'photos' => $photoCount,
                'videos' => $videoCount,
                'voice' => $voiceStorageId ? 1 : 0,
                'messages' => $normalizedMessage !== '' ? 1 : 0,
                'storage_bytes' => $totalSize + $voiceSize,
            ]);

            return $lockedSubmission->fresh();
        });
    }

    public function findSubmissionById(int $submissionId): ?Submission
    {
        return Submission::find($submissionId);
    }

    public function deleteSubmission(Submission $submission): void
    {
        $submission->load(['media', 'event']);

        $resources = $this->cleanupService->collectSubmissionResourceIds($submission);

        DB::transaction(function () use ($submission, $resources) {
            $lockedSubmission = Submission::with('event')->lockForUpdate()->find($submission->id);
            if (! $lockedSubmission) {
                return;
            }

            $this->cleanupService->enqueueDeletions($resources);

            if ($lockedSubmission->isCompleted()) {
                $this->eventService->incrementCounters($lockedSubmission->event, [
                    'submissions' => -1,
                    'photos' => -$lockedSubmission->total_photos,
                    'videos' => -$lockedSubmission->total_videos,
                    'voice' => $lockedSubmission->hasVoice() ? -1 : 0,
                    'messages' => $lockedSubmission->hasMessage() ? -1 : 0,
                    'storage_bytes' => -($lockedSubmission->total_size_bytes + ($lockedSubmission->voice_size_bytes ?? 0)),
                ]);
            }

            $lockedSubmission->delete();
        });
    }
}
