<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\System\HealthCheckService;
use Illuminate\Http\JsonResponse;

/**
 * Controller handling system health and diagnostics endpoints.
 */
class HealthController extends ApiController
{
    /**
     * Check infrastructure health status across Database, Redis, Cache and Queue.
     */
    public function index(HealthCheckService $healthService): JsonResponse
    {
        $health = $healthService->checkHealth();
        $statusCode = match ($health->status) {
            'healthy' => 200,
            'degraded' => 200,
            default => 503,
        };

        return $this->respondWithSuccess(
            data: $health->toArray(),
            message: 'System health probe completed.',
            statusCode: $statusCode,
        );
    }

    /**
     * Return API v1 version and metadata.
     */
    public function version(): JsonResponse
    {
        return $this->respondWithSuccess(
            data: [
                'name' => 'Bamcom AI CRM API',
                'version' => 'v1',
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'environment' => config('app.env'),
                'timezone' => config('app.timezone'),
            ],
            message: 'API v1 metadata retrieved.',
        );
    }
}
