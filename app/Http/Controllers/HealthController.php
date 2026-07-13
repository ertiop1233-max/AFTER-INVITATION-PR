<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly HealthService $healthService,
    ) {}

    public function check(): JsonResponse
    {
        $health = $this->healthService->check();

        return response()->json([
            'status' => $health['database'] && $health['drive'] ? 'healthy' : 'degraded',
            'database' => $health['database'],
            'drive' => $health['drive'],
            'smtp' => $health['smtp'],
        ]);
    }
}
