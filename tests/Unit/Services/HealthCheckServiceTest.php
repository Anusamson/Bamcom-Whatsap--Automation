<?php

namespace Tests\Unit\Services;

use App\DTOs\System\HealthStatusDTO;
use App\Services\System\HealthCheckService;
use Tests\TestCase;

class HealthCheckServiceTest extends TestCase
{
    private HealthCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(HealthCheckService::class);
    }

    public function test_check_database_returns_healthy_in_test_environment(): void
    {
        $db = $this->service->checkDatabase();

        $this->assertSame('healthy', $db['status']);
        $this->assertNotNull($db['latency_ms']);
    }

    public function test_check_cache_returns_healthy_for_configured_store(): void
    {
        $cache = $this->service->checkCache();

        $this->assertSame('healthy', $cache['status']);
        $this->assertSame('array', $cache['driver']);
    }

    public function test_check_queue_returns_healthy_for_sync_driver(): void
    {
        $queue = $this->service->checkQueue();

        $this->assertSame('healthy', $queue['status']);
        $this->assertSame('sync', $queue['driver']);
    }

    public function test_check_health_returns_aggregated_dto(): void
    {
        $health = $this->service->checkHealth();

        $this->assertInstanceOf(HealthStatusDTO::class, $health);
        $this->assertContains($health->status, ['healthy', 'degraded', 'unhealthy']);
        $this->assertSame('testing', $health->environment);
        $this->assertArrayHasKey('database', $health->toArray()['services']);
    }
}
