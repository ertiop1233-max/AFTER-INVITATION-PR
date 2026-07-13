<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class UploadService
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly CleanupService $cleanupService,
    ) {}

    public function initUpload(Submission $submission, array $fileData): array
    {
        $mediaType = $this->determineMediaType($fileData['mime_type']);

        $storedFilename = Str::uuid()->toString() . '.' . $fileData['extension'];

        $media = Media::create([
            'submission_id' => $submission->id,
            'event_id' => $submission->event_id,
            'media_type' => $mediaType,
            'original_filename' => $fileData['original_filename'],
            'stored_filename' => $storedFilename,
            'mime_type' => $fileData['mime_type'],
            'extension' => $fileData['extension'],
            'file_size_bytes' => $fileData['file_size_bytes'],
            'width' => $fileData['width'] ?? null,
            'height' => $fileData['height'] ?? null,
            'duration_seconds' => $fileData['duration_seconds'] ?? null,
            'storage_path' => "evt_{$submission->event->upload_slug}/sub_{$submission->storage_folder_id}/{$storedFilename}",
            'status' => Media::STATUS_UPLOADING,
        ]);

        $resumableUri = $this->storageService->createResumableUpload(
            $submission->storage_folder_id,
            $storedFilename,
            $fileData['mime_type'],
            $fileData['file_size_bytes']
        );

        $strategy = config('memoryvault.upload_strategy');

        if ($strategy === 'plan_b') {
            $media->update(['resumable_uri' => $resumableUri]);
            return ['media' => $media->fresh(), 'upload_uri' => null];
        }

        return ['media' => $media, 'upload_uri' => $resumableUri];
    }

    public function processChunk(Media $media, string $data, int $offset, int $totalSize): array
    {
        if (!$media->resumable_uri) {
            throw new RuntimeException('Media has no resumable upload URI');
        }

        $result = $this->storageService->uploadChunk(
            $media->resumable_uri,
            $data,
            $offset,
            $totalSize
        );

        if ($result['completed']) {
            $this->completeUploadFromMetadata($media, $result['file_id'], $result['size']);
        }

        return $result;
    }

    public function completeUploadByLookup(Media $media): Media
    {
        $submission = $media->submission;

        $fileInfo = $this->storageService->findFileInFolderByName(
            $submission->storage_folder_id,
            $media->stored_filename
        );

        if (!$fileInfo) {
            $media->update(['status' => Media::STATUS_FAILED]);
            throw new RuntimeException('Uploaded file not found in submission folder');
        }

        if ((int) $fileInfo['size'] !== (int) $media->file_size_bytes) {
            $media->update(['status' => Media::STATUS_FAILED]);
            throw new RuntimeException('File size mismatch');
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
        if (!$media->storage_id) {
            return null;
        }

        $submission = $media->submission;
        $event = $submission->event;

        $thumbsFolderId = $this->ensureThumbnailsFolder($event);

        $thumbFilename = $media->id . '_thumb.jpg';

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
        $fileName = Str::uuid()->toString() . '_voice.' . $this->getExtensionFromMime($mimeType);

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
        if ($media->storage_id) {
            $this->cleanupService->enqueueFileDeletion($media->storage_id);
        }
        if ($media->thumbnail_storage_id) {
            $this->cleanupService->enqueueFileDeletion($media->thumbnail_storage_id);
        }

        $media->delete();

        $this->cleanupService->processPendingJobs();
    }

    private function completeUploadFromMetadata(Media $media, string $fileId, int $fileSize): Media
    {
        $media->update([
            'storage_id' => $fileId,
            'status' => Media::STATUS_UPLOADED,
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
}
