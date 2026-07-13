<?php

namespace App\Services;

use App\Services\Storage\StorageProviderInterface;
use Illuminate\Support\Facades\Log;

class StorageService
{
    public function __construct(
        private readonly StorageProviderInterface $provider,
    ) {}

    public function checkHealth(): bool
    {
        return $this->provider->checkHealth();
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        return $this->provider->createFolder($name, $parentId);
    }

    public function deleteFolder(string $folderId): void
    {
        try {
            $this->provider->deleteFolder($folderId);
        } catch (\Throwable $e) {
            Log::error('Failed to delete Drive folder', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $this->provider->deleteFile($fileId);
        } catch (\Throwable $e) {
            Log::error('Failed to delete Drive file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createResumableUpload(string $folderId, string $fileName, string $mimeType, int $fileSize): string
    {
        return $this->provider->createResumableUpload($folderId, $fileName, $mimeType, $fileSize);
    }

    public function uploadSmallFile(string $folderId, string $fileName, string $mimeType, string $content): string
    {
        return $this->provider->uploadSmallFile($folderId, $fileName, $mimeType, $content);
    }

    public function uploadChunk(string $resumableUri, string $data, int $offset, int $totalSize): array
    {
        return $this->provider->uploadChunk($resumableUri, $data, $offset, $totalSize);
    }

    public function getFileMetadata(string $fileId): array
    {
        return $this->provider->getFileMetadata($fileId);
    }

    public function findFileInFolderByName(string $folderId, string $fileName): ?array
    {
        return $this->provider->findFileInFolderByName($folderId, $fileName);
    }

    public function getFileStream(string $fileId): mixed
    {
        return $this->provider->getFileStream($fileId);
    }

    public function generateViewUrl(string $fileId): string
    {
        return $this->provider->generateViewUrl($fileId);
    }

    public function getQuotaInfo(): ?array
    {
        return $this->provider->getQuotaInfo();
    }
}
