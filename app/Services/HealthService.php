<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HealthService
{
    public function __construct(
        private readonly StorageService $storageService,
    ) {}

    public function check(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'drive' => $this->checkDrive(),
            'drive_quota' => $this->checkDriveQuota(),
            'smtp' => $this->checkSmtp(),
        ];
    }

    public function isHealthy(): bool
    {
        $health = $this->check();
        return $health['database'] && $health['drive'];
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function checkDrive(): bool
    {
        try {
            return $this->storageService->checkHealth();
        } catch (\Throwable $e) {
            Log::error('Drive health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function checkDriveQuota(): ?array
    {
        try {
            return $this->storageService->getQuotaInfo();
        } catch (\Throwable $e) {
            Log::warning('Drive quota check failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function checkSmtp(): bool
    {
        try {
            $mailer = Mail::mailer();
            $transport = $mailer->getSymfonyTransport();

            if (method_exists($transport, 'start')) {
                $transport->start();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('SMTP health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
