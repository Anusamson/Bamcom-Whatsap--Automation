<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthCheckApiTest extends TestCase
{
    public function test_api_v1_health_probe_returns_success_payload(): void
    {
        $response = $this->getJson(route('api.v1.health'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'environment',
                    'timestamp',
                    'services' => [
                        'database' => ['status', 'message'],
                        'redis' => ['status', 'message'],
                        'cache' => ['status', 'message', 'driver'],
                        'queue' => ['status', 'message', 'driver'],
                    ],
                ],
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_api_v1_version_endpoint_returns_metadata(): void
    {
        $response = $this->getJson(route('api.v1.version'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Bamcom AI CRM API',
                    'version' => 'v1',
                ],
            ]);
    }
}
