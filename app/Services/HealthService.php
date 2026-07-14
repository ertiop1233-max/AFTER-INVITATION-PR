<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
            'quota_last_checked' => $this->getQuotaLastChecked(),
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
        return Cache::remember('memoryvault.health.drive', now()->addMinute(), function (): bool {
            try {
                return $this->storageService->checkHealth();
            } catch (\Throwable $e) {
                Log::error('Drive health check failed', ['error' => $e->getMessage()]);

                return false;
            }
        });
    }

    private function checkDriveQuota(): ?array
    {
        return Cache::remember('memoryvault.drive_quota', now()->addHour(), function (): ?array {
            try {
                $quota = $this->storageService->getQuotaInfo();

                if ($quota !== null) {
                    Cache::put('memoryvault.quota_last_checked', now()->toIso8601String(), now()->addHours(1));
                }

                return $quota;
            } catch (\Throwable $e) {
                Log::warning('Drive quota check failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function getQuotaLastChecked(): ?string
    {
        return Cache::get('memoryvault.quota_last_checked');
    }

    private function checkSmtp(): bool
    {
        return Cache::remember('memoryvault.health.smtp', now()->addMinutes(5), function (): bool {
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
        });
    }
}
