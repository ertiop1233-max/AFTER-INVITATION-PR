<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
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
            Log::error('Local QR generation failed');

            return null;
        }

        $existing = $this->findQrFile($folderId);

        try {
            $fileId = $this->storageService->uploadSmallFile(
                $folderId,
                self::QR_FILENAME,
                'image/png',
                $pngData
            );

            if ($existing && $existing['id'] !== $fileId) {
                try {
                    $this->storageService->deleteFile($existing['id']);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete replaced QR code', ['error' => $e->getMessage()]);
                }
            }

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

        if (! $file) {
            return null;
        }

        return $this->storageService->getFileStream($file['id']);
    }

    public function generatePng(string $url): ?string
    {
        try {
            if (! class_exists(QRCode::class)) {
                return null;
            }

            if (! extension_loaded('gd')) {
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
}
