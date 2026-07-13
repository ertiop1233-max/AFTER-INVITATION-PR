<?php

namespace App\Console\Commands;

use App\Services\CleanupService;
use Illuminate\Console\Command;

class CleanupOrphanedUploads extends Command
{
    protected $signature = 'memoryvault:cleanup';
    protected $description = 'Clean up orphaned draft submissions and stale media';

    public function handle(CleanupService $cleanupService): int
    {
        $this->info('Starting cleanup of orphaned uploads...');

        $cleanupService->cleanupOrphanedUploads();

        $this->info('Cleanup complete.');
        return self::SUCCESS;
    }
}
