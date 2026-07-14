<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class UploadService
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly CleanupService $cleanupService,
        private readonly EventService $eventService,
    ) {}

    public function initUpload(Submission $submission, array $fileData): array
    {
        $media = DB::transaction(function () use ($submission, $fileData): Media {
            $lockedSubmission = Submission::with('event')->lockForUpdate()->find($submission->id);

            if (! $lockedSubmission || ! $lockedSubmission->isDraft()) {
                throw new DomainException('Submission is no longer accepting uploads.');
            }

            if ($lockedSubmission->event->isClosed() || $lockedSubmission->event->isUploadDeadlinePassed()) {
                throw new DomainException('This event is no longer accepting submissions.');
            }

            $reservedBytes = (int) $lockedSubmission->media()
                ->whereIn('status', [Media::STATUS_UPLOADING, Media::STATUS_UPLOADED])
                ->sum('file_size_bytes');

            if ($reservedBytes + (int) $fileData['file_size_bytes'] > $lockedSubmission->event->max_submission_size_bytes) {
                throw new DomainException('File exceeds remaining submission size limit.');
            }

            $mediaType = $this->determineMediaType($fileData['mime_type']);
            $storedFilename = Str::uuid()->toString().'.'.$fileData['extension'];

            return Media::create([
                'submission_id' => $lockedSubmission->id,
                'event_id' => $lockedSubmission->event_id,
                'media_type' => $mediaType,
                'original_filename' => $fileData['original_filename'],
                'stored_filename' => $storedFilename,
                'mime_type' => $fileData['mime_type'],
                'extension' => $fileData['extension'],
                'file_size_bytes' => $fileData['file_size_bytes'],
                'width' => $fileData['width'] ?? null,
                'height' => $fileData['height'] ?? null,
                'duration_seconds' => $fileData['duration_seconds'] ?? null,
                'storage_path' => "evt_{$lockedSubmission->event->upload_slug}/sub_{$lockedSubmission->storage_folder_id}/{$storedFilename}",
                'status' => Media::STATUS_UPLOADING,
                'uploaded_bytes' => 0,
            ]);
        });

        try {
            $resumableUri = $this->storageService->createResumableUpload(
                $media->submission->storage_folder_id,
                $media->stored_filename,
                $media->mime_type,
                $media->file_size_bytes
            );
        } catch (\Throwable $e) {
            $media->delete();
            throw $e;
        }

        $strategy = config('memoryvault.upload_strategy');

        if ($strategy === 'plan_b') {
            $media->update(['resumable_uri' => $resumableUri]);

            return ['media' => $media->fresh(), 'upload_uri' => null];
        }

        return ['media' => $media, 'upload_uri' => $resumableUri];
    }

    public function processChunk(Media $media, string $data, int $offset, int $totalSize): array
    {
        if ($offset === 0) {
            try {
                $this->assertValidFileSignature($media, $data);
            } catch (DomainException $e) {
                Media::whereKey($media->id)
                    ->where('status', Media::STATUS_UPLOADING)
                    ->update([
                        'status' => Media::STATUS_FAILED,
                        'resumable_uri' => null,
                    ]);
                throw $e;
            }
        }

        return DB::transaction(function () use ($media, $data, $offset, $totalSize): array {
            $lockedMedia = Media::with('submission')->lockForUpdate()->find($media->id);

            if (! $lockedMedia || ! $lockedMedia->resumable_uri || $lockedMedia->status !== Media::STATUS_UPLOADING) {
                throw new DomainException('Media is no longer accepting upload chunks.');
            }

            if (! $lockedMedia->submission->isDraft()) {
                throw new DomainException('Submission is no longer accepting uploads.');
            }

            if ($totalSize !== $lockedMedia->file_size_bytes || $offset !== $lockedMedia->uploaded_bytes) {
                throw new DomainException('Upload chunk is out of sequence.');
            }

            $result = $this->storageService->uploadChunk(
                $lockedMedia->resumable_uri,
                $data,
                $offset,
                $totalSize
            );

            if ($result['completed']) {
                $fileId = $result['file_id'] ?? null;
                if (! $fileId) {
                    throw new RuntimeException('Storage provider did not return a completed file ID.');
                }
                $this->completeUploadFromMetadata($lockedMedia, $fileId, (int) $result['size']);
            } else {
                $lockedMedia->update([
                    'uploaded_bytes' => (int) ($result['uploaded_bytes'] ?? ($offset + strlen($data))),
                ]);
            }

            return $result;
        });
    }

    public function completeUploadByLookup(Media $media): Media
    {
        $submission = $media->submission;

        $fileInfo = $this->storageService->findFileInFolderByName(
            $submission->storage_folder_id,
            $media->stored_filename
        );

        if (! $fileInfo) {
            $media->update(['status' => Media::STATUS_FAILED]);
            throw new RuntimeException('Uploaded file not found in submission folder');
        }

        if ((int) $fileInfo['size'] !== (int) $media->file_size_bytes) {
            $media->update(['status' => Media::STATUS_FAILED]);
            throw new RuntimeException('File size mismatch');
        }

        try {
            $stream = $this->storageService->getFileStream($fileInfo['id']);
            $header = $stream->read(32);
            if (method_exists($stream, 'close')) {
                $stream->close();
            }
            $this->assertValidFileSignature($media, $header);
        } catch (DomainException $e) {
            $media->update(['status' => Media::STATUS_FAILED]);
            $this->cleanupService->enqueueFileDeletion($fileInfo['id']);
            throw $e;
        }

        return $this->completeUploadFromMetadata($media, $fileInfo['id'], (int) $fileInfo['size']);
    }

    public function markAsFailed(Media $media): Media
    {
        $media->update(['status' => Media::STATUS_FAILED]);

        return $media->fresh();
    }

    public function uploadThumbnail(Media $media, string $thumbnailData): ?string
    {
        if (! $media->storage_id) {
            return null;
        }

        $submission = $media->submission;
        $event = $submission->event;

        $thumbsFolderId = $this->ensureThumbnailsFolder($event);

        $thumbFilename = $media->id.'_thumb.jpg';

        try {
            $thumbFileId = $this->storageService->uploadSmallFile(
                $thumbsFolderId,
                $thumbFilename,
                'image/jpeg',
                $thumbnailData
            );

            $media->update([
                'thumbnail_storage_id' => $thumbFileId,
                'thumbnail_path' => "evt_{$event->upload_slug}/_thumbs/{$thumbFilename}",
            ]);

            return $thumbFileId;
        } catch (\Throwable $e) {
            Log::warning('Thumbnail upload failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function uploadVoice(Submission $submission, string $voiceData, string $mimeType, int $durationSeconds): ?string
    {
        $fileName = Str::uuid()->toString().'_voice.'.$this->getExtensionFromMime($mimeType);
        $previousFileId = $submission->voice_storage_id;

        try {
            $fileId = $this->storageService->uploadSmallFile(
                $submission->storage_folder_id,
                $fileName,
                $mimeType,
                $voiceData
            );

            $submission->update([
                'voice_storage_id' => $fileId,
                'voice_storage_path' => "evt_{$submission->event->upload_slug}/sub_{$submission->storage_folder_id}/{$fileName}",
                'voice_duration_seconds' => $durationSeconds,
                'voice_size_bytes' => strlen($voiceData),
            ]);

            if ($previousFileId && $previousFileId !== $fileId) {
                $this->cleanupService->enqueueFileDeletion($previousFileId);
            }

            return $fileId;
        } catch (\Throwable $e) {
            Log::error('Voice upload failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Unable to upload voice recording');
        }
    }

    public function deleteMedia(Media $media): void
    {
        DB::transaction(function () use ($media): void {
            $lockedMedia = Media::with(['submission.event'])->lockForUpdate()->find($media->id);
            if (! $lockedMedia) {
                return;
            }

            if ($lockedMedia->storage_id) {
                $this->cleanupService->enqueueFileDeletion($lockedMedia->storage_id);
            }
            if ($lockedMedia->thumbnail_storage_id) {
                $this->cleanupService->enqueueFileDeletion($lockedMedia->thumbnail_storage_id);
            }

            $submission = $lockedMedia->submission;
            if ($lockedMedia->isUploaded() && $submission->isCompleted()) {
                $photoDelta = $lockedMedia->isPhoto() ? -1 : 0;
                $videoDelta = $lockedMedia->isVideo() ? -1 : 0;

                $submission->update([
                    'total_photos' => max(0, $submission->total_photos + $photoDelta),
                    'total_videos' => max(0, $submission->total_videos + $videoDelta),
                    'total_size_bytes' => max(0, $submission->total_size_bytes - $lockedMedia->file_size_bytes),
                ]);

                $this->eventService->incrementCounters($submission->event, [
                    'photos' => $photoDelta,
                    'videos' => $videoDelta,
                    'storage_bytes' => -$lockedMedia->file_size_bytes,
                ]);
            }

            $lockedMedia->delete();
        });
    }

    private function completeUploadFromMetadata(Media $media, string $fileId, int $fileSize): Media
    {
        if ($fileSize !== $media->file_size_bytes) {
            $media->update(['status' => Media::STATUS_FAILED]);
            throw new DomainException('File size mismatch.');
        }

        $media->update([
            'storage_id' => $fileId,
            'status' => Media::STATUS_UPLOADED,
            'uploaded_bytes' => $fileSize,
            'resumable_uri' => null,
            'uploaded_at' => now(),
        ]);

        return $media->fresh();
    }

    private function ensureThumbnailsFolder(Event $event): string
    {
        $existing = $this->storageService->findFileInFolderByName(
            $event->storage_root_folder_id,
            '_thumbs'
        );

        if ($existing) {
            return $existing['id'];
        }

        return $this->storageService->createFolder('_thumbs', $event->storage_root_folder_id);
    }

    private function determineMediaType(string $mimeType): string
    {
        $imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        $videoMimes = ['video/mp4', 'video/quicktime', 'video/webm'];

        if (in_array($mimeType, $imageMimes)) {
            return Media::TYPE_PHOTO;
        }

        if (in_array($mimeType, $videoMimes)) {
            return Media::TYPE_VIDEO;
        }

        throw new RuntimeException("Unsupported MIME type: {$mimeType}");
    }

    private function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            default => 'bin',
        };
    }

    private function assertValidFileSignature(Media $media, string $data): void
    {
        $matches = match ($media->mime_type) {
            'image/jpeg' => str_starts_with($data, "\xFF\xD8\xFF"),
            'image/png' => str_starts_with($data, "\x89PNG\r\n\x1A\n"),
            'image/webp' => substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP',
            'image/heic', 'image/heif' => substr($data, 4, 4) === 'ftyp'
                && in_array(substr($data, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true),
            'video/mp4', 'video/quicktime' => substr($data, 4, 4) === 'ftyp',
            'video/webm' => str_starts_with($data, "\x1A\x45\xDF\xA3"),
            default => false,
        };

        if (! $matches) {
            throw new DomainException('Uploaded content does not match the declared file type.');
        }
    }
}
