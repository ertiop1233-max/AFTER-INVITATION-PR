<?php

namespace App\Console\Commands;

use App\Services\HealthService;
use Illuminate\Console\Command;

class HealthCheck extends Command
{
    protected $signature = 'memoryvault:health';

    protected $description = 'Run system health checks';

    public function handle(HealthService $healthService): int
    {
        $health = $healthService->check();

        $allHealthy = true;

        foreach ($health as $component => $status) {
            $isHealthy = is_bool($status) ? $status : ($status !== null);
            $label = $isHealthy ? 'OK' : 'FAIL';

            if (! $isHealthy) {
                $allHealthy = false;
            }

            $this->line("  {$component}: {$label}");
        }

        return $allHealthy ? self::SUCCESS : self::FAILURE;
    }
}
