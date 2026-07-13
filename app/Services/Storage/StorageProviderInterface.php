<?php

namespace App\Services\Storage;

interface StorageProviderInterface
{
    public function checkHealth(): bool;

    public function createFolder(string $name, ?string $parentId = null): string;

    public function deleteFolder(string $folderId): void;

    public function deleteFile(string $fileId): void;

    public function createResumableUpload(string $folderId, string $fileName, string $mimeType, int $fileSize): string;

    public function uploadSmallFile(string $folderId, string $fileName, string $mimeType, string $content): string;

    public function uploadChunk(string $resumableUri, string $data, int $offset, int $totalSize): array;

    public function getFileMetadata(string $fileId): array;

    public function findFileInFolderByName(string $folderId, string $fileName): ?array;

    public function getFileStream(string $fileId): mixed;

    public function generateViewUrl(string $fileId): string;

    public function getQuotaInfo(): ?array;
}
