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

        $healthy = $health['database'] && $health['drive'];

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'database' => $health['database'],
            'drive' => $health['drive'],
            'smtp' => $health['smtp'],
        ], $healthy ? 200 : 503);
    }
}
