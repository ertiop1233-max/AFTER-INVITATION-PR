<?php

namespace App\Services\Storage;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleDriveProvider implements StorageProviderInterface
{
    private ?Google_Service_Drive $service = null;

    public function __construct(
        private readonly ?string $serviceAccountKey = null,
        private readonly ?string $serviceAccountKeyFile = null,
        private readonly ?string $sharedDriveId = null,
        private readonly ?string $storageRootFolderId = null,
    ) {}

    public function checkHealth(): bool
    {
        try {
            if ($this->sharedDriveId) {
                $this->getService()->drives->get($this->sharedDriveId);
            } else {
                $rootId = $this->storageRootFolderId;
                if (! $rootId) {
                    Log::error('Google Drive health check failed: no root folder ID configured');

                    return false;
                }
                $this->getService()->files->get($rootId, $this->baseParams([
                    'fields' => 'id',
                ]));
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Google Drive health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function createFolder(string $name, ?string $parentId = null): string
    {
        $effectiveParentId = $parentId ?? $this->getRootFolderId();

        $file = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($effectiveParentId !== null) {
            $file->setParents([$effectiveParentId]);
        }

        $params = $this->baseParams([
            'fields' => 'id',
        ]);

        $created = $this->getService()->files->create($file, $params);

        return $created->id;
    }

    public function deleteFolder(string $folderId): void
    {
        $this->getService()->files->delete($folderId, $this->baseParams([]));
    }

    public function deleteFile(string $fileId): void
    {
        $this->getService()->files->delete($fileId, $this->baseParams([]));
    }

    public function createResumableUpload(string $folderId, string $fileName, string $mimeType, int $fileSize): string
    {
        $file = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
        ]);

        $file->setParents([$folderId]);

        $params = $this->baseParams([
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
        $file = new Google_Service_Drive_DriveFile([
            'name' => $fileName,
        ]);

        $file->setParents([$folderId]);

        $params = $this->baseParams([
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

        $request = new Request('PUT', $resumableUri, [
            'Content-Length' => strlen($data),
            'Content-Range' => "bytes {$offset}-".($offset + strlen($data) - 1)."/{$totalSize}",
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
        $file = $this->getService()->files->get($fileId, $this->baseParams([
            'fields' => 'id,name,size,md5Checksum,mimeType,parents',
        ]));

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
        $escapedFolderId = $this->escapeQueryLiteral($folderId);
        $escapedFileName = $this->escapeQueryLiteral($fileName);
        $query = "'{$escapedFolderId}' in parents and name = '{$escapedFileName}' and trashed = false";

        $params = $this->listParams([
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
        $client = $this->getService()->getClient();

        $url = sprintf(
            'https://www.googleapis.com/drive/v3/files/%s?alt=media',
            $fileId
        );

        $request = new Request('GET', $url);

        $response = $client->execute($request);

        return $response->getBody();
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

            if (! $quota) {
                return null;
            }

            return [
                'limit' => (int) $quota->getLimit(),
                'usage' => (int) $quota->getUsage(),
                'usage_in_drive' => (int) $quota->getUsageInDrive(),
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
        $client = new Google_Client;
        $client->setAuthConfig($this->getCredentialsArray());
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setHttpClient(new Client([
            'timeout' => 120,
            'verify' => true,
        ]));

        return $client;
    }

    private function getCredentialsArray(): array
    {
        if ($this->serviceAccountKeyFile && file_exists($this->serviceAccountKeyFile)) {
            $json = file_get_contents($this->serviceAccountKeyFile);

            return $this->validateCredentialsJson($json === false ? '' : $json);
        }

        if ($this->serviceAccountKey) {
            $encoded = str_starts_with($this->serviceAccountKey, 'base64:')
                ? substr($this->serviceAccountKey, 7)
                : $this->serviceAccountKey;
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                throw new RuntimeException('GOOGLE_SERVICE_ACCOUNT_KEY is not valid base64');
            }

            return $this->validateCredentialsJson($decoded);
        }

        throw new RuntimeException('No Google service account credentials configured');
    }

    private function getRootFolderId(): ?string
    {
        if ($this->sharedDriveId) {
            return $this->sharedDriveId;
        }

        return $this->storageRootFolderId;
    }

    private function baseParams(array $params): array
    {
        return array_merge([
            'supportsAllDrives' => true,
        ], $params);
    }

    private function listParams(array $params): array
    {
        $params = $this->baseParams($params);

        if ($this->sharedDriveId) {
            $params = array_merge($params, [
                'includeItemsFromAllDrives' => true,
                'corpora' => 'drive',
                'driveId' => $this->sharedDriveId,
            ]);
        }

        return $params;
    }

    private function getChunkSize(): int
    {
        return config('memoryvault.upload_chunk_size', 8 * 1024 * 1024);
    }

    private function escapeQueryLiteral(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function validateCredentialsJson(string $json): array
    {
        $credentials = json_decode($json, true);

        if (! is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account'
            || empty($credentials['client_email'])
            || empty($credentials['private_key'])) {
            throw new RuntimeException('Google service-account key JSON is invalid or incomplete');
        }

        return $credentials;
    }
}
