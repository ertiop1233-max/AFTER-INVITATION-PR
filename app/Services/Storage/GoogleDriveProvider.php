<?php

namespace App\Services\Storage;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleDriveProvider implements StorageProviderInterface
{
    private ?Google_Service_Drive $service = null;

    public function __construct(
        private readonly ?string $serviceAccountKey = null,
        private readonly ?string $serviceAccountKeyFile = null,
        private readonly ?string $sharedDriveId = null,
    ) {}

    public function checkHealth(): bool
    {
        try {
            $this->getService()->drives->get($this->sharedDriveId);
            return true;
        } catch (\Throwable $e) {
            Log::error('Google Drive health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        $file = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId !== null) {
            $file->setParents([$parentId]);
        }

        $params = $this->withSupportsAllDrive([
            'fields' => 'id',
        ]);

        $created = $this->getService()->files->create($file, $params);

        return $created->id;
    }

    public function deleteFolder(string $folderId): void
    {
        $params = $this->withSupportsAllDrive([]);

        $this->getService()->files->delete($folderId, $params);
    }

    public function deleteFile(string $fileId): void
    {
        $params = $this->withSupportsAllDrive([]);

        $this->getService()->files->delete($fileId, $params);
    }

    public function createResumableUpload(string $folderId, string $fileName, string $mimeType, int $fileSize): string
    {
        $file = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
        ]);

        $file->setParents([$folderId]);

        $params = $this->withSupportsAllDrive([
            'uploadType' => 'resumable',
            'fields' => 'id,size,md5Checksum',
        ]);

        $client = $this->getService()->getClient();
        $client->setDefer(true);

        $request = $this->getService()->files->create($file, $params);

        $media = new \Google_Http_MediaFileUpload(
            $client,
            $request,
            $mimeType,
            null,
            true,
            $this->getChunkSize()
        );
        $media->setFileSize($fileSize);

        $uri = $media->getResumeUri();

        $client->setDefer(false);

        return $uri;
    }

    public function uploadSmallFile(string $folderId, string $fileName, string $mimeType, string $content): string
    {
        $client = $this->getService()->getClient();

        $file = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
        ]);

        $file->setParents([$folderId]);

        $params = $this->withSupportsAllDrive([
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        $created = $this->getService()->files->create($file, $params);

        return $created->id;
    }

    public function uploadChunk(string $resumableUri, string $data, int $offset, int $totalSize): array
    {
        $client = $this->getService()->getClient();
        $client->setDefer(true);

        $request = new \GuzzleHttp\Psr7\Request('PUT', $resumableUri, [
            'Content-Length' => strlen($data),
            'Content-Range' => "bytes {$offset}-" . ($offset + strlen($data) - 1) . "/{$totalSize}",
        ], $data);

        $response = $client->execute($request);

        $client->setDefer(false);

        $code = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($code === 200 || $code === 201) {
            $metadata = json_decode($body, true);
            return [
                'completed' => true,
                'file_id' => $metadata['id'] ?? null,
                'size' => (int) ($metadata['size'] ?? 0),
                'md5' => $metadata['md5Checksum'] ?? null,
            ];
        }

        if ($code === 308) {
            $range = $response->getHeaderLine('Range');
            return [
                'completed' => false,
                'uploaded_bytes' => $range ? (int) explode('-', $range)[1] + 1 : $offset + strlen($data),
            ];
        }

        throw new RuntimeException("Unexpected response code {$code} during chunk upload: {$body}");
    }

    public function getFileMetadata(string $fileId): array
    {
        $params = $this->withSupportsAllDrive([
            'fields' => 'id,name,size,md5Checksum,mimeType,parents',
        ]);

        $file = $this->getService()->files->get($fileId, $params);

        return [
            'id' => $file->id,
            'name' => $file->name,
            'size' => (int) $file->size,
            'md5' => $file->md5Checksum,
            'mime_type' => $file->mimeType,
            'parents' => $file->parents,
        ];
    }

    public function findFileInFolderByName(string $folderId, string $fileName): ?array
    {
        $query = "'{$folderId}' in parents and name = '{$fileName}' and trashed = false";

        $params = $this->withSupportsAllDrive([
            'q' => $query,
            'fields' => 'files(id,name,size,md5Checksum,mimeType)',
            'pageSize' => 2,
        ]);

        $results = $this->getService()->files->listFiles($params);
        $files = $results->getFiles();

        if (count($files) === 0) {
            return null;
        }

        $file = $files[0];

        return [
            'id' => $file->id,
            'name' => $file->name,
            'size' => (int) $file->size,
            'md5' => $file->md5Checksum,
            'mime_type' => $file->mimeType,
        ];
    }

    public function getFileStream(string $fileId): mixed
    {
        $params = $this->withSupportsAllDrive([
            'alt' => 'media',
        ]);

        $url = $this->getService()->files->get($fileId, $params)->getDownloadUrl();

        if (!$url) {
            throw new RuntimeException("Cannot get download URL for file {$fileId}");
        }

        $client = $this->getService()->getClient();
        $request = new \GuzzleHttp\Psr7\Request('GET', $url);

        return $client->execute($request)->getBody();
    }

    public function generateViewUrl(string $fileId): string
    {
        return "https://drive.google.com/uc?id={$fileId}";
    }

    public function getQuotaInfo(): ?array
    {
        try {
            $about = $this->getService()->about->get([
                'fields' => 'storageQuota,kind',
            ]);

            $quota = $about->getStorageQuota();

            if (!$quota) {
                return null;
            }

            return [
                'limit' => (int) $quota->getLimit(),
                'usage' => (int) $quota->getUsageInDrive(),
                'usage_in_drive' => (int) $quota->getUsage(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Could not retrieve Drive quota info', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function getService(): Google_Service_Drive
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $this->service = new Google_Service_Drive($this->createClient());
        return $this->service;
    }

    private function createClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setAuthConfig($this->getCredentialsArray());
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => 120,
            'verify' => true,
        ]));

        return $client;
    }

    private function getCredentialsArray(): array
    {
        if ($this->serviceAccountKeyFile && file_exists($this->serviceAccountKeyFile)) {
            $json = file_get_contents($this->serviceAccountKeyFile);
            return json_decode($json, true);
        }

        if ($this->serviceAccountKey) {
            $decoded = base64_decode($this->serviceAccountKey, true);
            if ($decoded === false) {
                throw new RuntimeException('GOOGLE_SERVICE_ACCOUNT_KEY is not valid base64');
            }
            return json_decode($decoded, true);
        }

        throw new RuntimeException('No Google service account credentials configured');
    }

    private function withSupportsAllDrive(array $params): array
    {
        return array_merge([
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'corpora' => 'drive',
            'driveId' => $this->sharedDriveId,
        ], $params);
    }

    private function getChunkSize(): int
    {
        return 8 * 1024 * 1024;
    }
}
