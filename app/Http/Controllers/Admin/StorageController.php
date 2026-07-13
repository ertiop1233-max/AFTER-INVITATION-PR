<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HealthService;

class StorageController extends Controller
{
    public function __construct(
        private readonly HealthService $healthService,
    ) {}

    public function index()
    {
        $health = $this->healthService->check();
        $quota = $health['drive_quota'];
        $quotaLastChecked = $health['quota_last_checked'];

        $quotaWarningPercent = config('memoryvault.drive_quota_warning_percent', 80);
        $quotaWarning = false;

        if ($quota && isset($quota['limit']) && $quota['limit'] > 0) {
            $usedPercent = ($quota['usage'] / $quota['limit']) * 100;
            $quotaWarning = $usedPercent >= $quotaWarningPercent;
        }

        return view('admin.storage', compact('health', 'quota', 'quotaWarning', 'quotaLastChecked'));
    }
}
