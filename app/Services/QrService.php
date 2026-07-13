<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrService
{
    private const QR_FILENAME = 'qr_code.png';

    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function generateAndStore(string $url, string $folderId): ?string
    {
        $pngData = $this->generatePng($url);

        if ($pngData === null) {
            $pngData = $this->generateViaFallback($url);
        }

        if ($pngData === null) {
            Log::error('QR generation failed: both local and fallback methods failed');
            return null;
        }

        $this->deleteExistingQr($folderId);

        try {
            $fileId = $this->storageService->uploadSmallFile(
                $folderId,
                self::QR_FILENAME,
                'image/png',
                $pngData
            );
            return $fileId;
        } catch (\Throwable $e) {
            Log::warning('QR upload failed, but event creation continues', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function findQrFile(string $folderId): ?array
    {
        return $this->storageService->findFileInFolderByName($folderId, self::QR_FILENAME);
    }

    public function getQrStream(string $folderId): mixed
    {
        $file = $this->findQrFile($folderId);

        if (!$file) {
            return null;
        }

        return $this->storageService->getFileStream($file['id']);
    }

    public function generatePng(string $url): ?string
    {
        try {
            if (!class_exists(QRCode::class)) {
                return null;
            }

            if (!extension_loaded('gd')) {
                return null;
            }

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_M,
                'imageBase64' => false,
                'scale' => 10,
            ]);

            return (new QRCode($options))->render($url);
        } catch (\Throwable $e) {
            Log::warning('Local QR generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function generateViaFallback(string $url): ?string
    {
        try {
            $apiUrl = config('memoryvault.qr.fallback_api_url');
            $response = Http::timeout(30)->get($apiUrl, [
                'data' => $url,
                'size' => '500x500',
                'format' => 'png',
            ]);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('QR fallback API returned error', ['status' => $response->status()]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('QR fallback API failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function deleteExistingQr(string $folderId): void
    {
        $existing = $this->findQrFile($folderId);

        if ($existing) {
            try {
                $this->storageService->deleteFile($existing['id']);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete existing QR code', ['error' => $e->getMessage()]);
            }
        }
    }
}
