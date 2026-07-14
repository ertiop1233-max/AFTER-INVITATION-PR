<?php

namespace App\Console\Commands;

use App\Services\CleanupService;
use Illuminate\Console\Command;

class ProcessDriveCleanupQueue extends Command
{
    protected $signature = 'memoryvault:process-cleanup-queue';

    protected $description = 'Process pending Drive cleanup jobs';

    public function handle(CleanupService $cleanupService): int
    {
        $this->info('Processing pending Drive cleanup jobs...');

        $cleanupService->processPendingJobs();

        $this->info('Drive cleanup queue processed.');

        return self::SUCCESS;
    }
}
