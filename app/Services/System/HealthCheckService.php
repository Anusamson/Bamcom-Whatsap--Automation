<?php

namespace App\Services\System;

use App\DTOs\System\HealthStatusDTO;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Service providing real-time diagnostics on system infrastructure.
 */
class HealthCheckService extends BaseService
{
    /**
     * Probe system infrastructure and return an aggregated HealthStatusDTO.
     */
    public function checkHealth(): HealthStatusDTO
    {
        $dbStatus = $this->checkDatabase();
        $redisStatus = $this->checkRedis();
        $cacheStatus = $this->checkCache();
        $queueStatus = $this->checkQueue();

        $overallStatus = ($dbStatus['status'] === 'healthy' && $redisStatus['status'] !== 'unhealthy')
            ? 'healthy'
            : ($dbStatus['status'] === 'healthy' ? 'degraded' : 'unhealthy');

        return new HealthStatusDTO(
            status: $overallStatus,
            environment: (string) config('app.env', 'production'),
            timestamp: now()->toIso8601String(),
            database: $dbStatus,
            redis: $redisStatus,
            cache: $cacheStatus,
            queue: $queueStatus,
        );
    }

    /**
     * Verify database connection and compute query round-trip latency.
     *
     * @return array{status: string, message: string, latency_ms?: ?float}
     */
    public function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'message' => 'Database connection operational',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $this->logError('Database health probe failed', [], $e);

            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: '.$e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * Verify Redis connection and measure ping latency.
     *
     * @return array{status: string, message: string, latency_ms?: ?float}
     */
    public function checkRedis(): array
    {
        $start = microtime(true);

        try {
            $redis = Redis::connection();
            $ping = $redis->ping();

            $latency = round((microtime(true) - $start) * 1000, 2);

            if ($ping === true || $ping === 'PONG' || (is_object($ping) && (string) $ping === 'PONG')) {
                return [
                    'status' => 'healthy',
                    'message' => 'Redis connection operational',
                    'latency_ms' => $latency,
                ];
            }

            return [
                'status' => 'degraded',
                'message' => 'Unexpected Redis ping response: '.(string) $ping,
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $this->logError('Redis health probe failed', [], $e);

            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection unreachable: '.$e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    /**
     * Probe application cache write/read operations.
     *
     * @return array{status: string, message: string, driver: string}
     */
    public function checkCache(): array
    {
        $driver = (string) config('cache.default', 'redis');
        $key = 'bamcom_health_probe_'.time();

        try {
            Cache::put($key, 'ok', 5);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'ok') {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache operations functional',
                    'driver' => $driver,
                ];
            }

            return [
                'status' => 'degraded',
                'message' => 'Cache probe read mismatch',
                'driver' => $driver,
            ];
        } catch (Throwable $e) {
            $this->logError('Cache health probe failed', [], $e);

            return [
                'status' => 'unhealthy',
                'message' => 'Cache storage unreachable: '.$e->getMessage(),
                'driver' => $driver,
            ];
        }
    }

    /**
     * Probe default queue connection status.
     *
     * @return array{status: string, message: string, driver: string}
     */
    public function checkQueue(): array
    {
        $driver = (string) config('queue.default', 'redis');

        try {
            Queue::connection();

            return [
                'status' => 'healthy',
                'message' => 'Queue system initialized',
                'driver' => $driver,
            ];
        } catch (Throwable $e) {
            $this->logError('Queue health probe failed', [], $e);

            return [
                'status' => 'unhealthy',
                'message' => 'Queue driver failure: '.$e->getMessage(),
                'driver' => $driver,
            ];
        }
    }
}
