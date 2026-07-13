<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrService
{
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

        $fileName = 'qr_' . Str::random(8) . '.png';

        try {
            $fileId = $this->storageService->uploadSmallFile(
                $folderId,
                $fileName,
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

    public function generatePng(string $url): ?string
    {
        try {
            if (!class_exists(QRCode::class)) {
                return null;
            }

            if (!function_exists('imagecreate') && !extension_loaded('gd')) {
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
}
