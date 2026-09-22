<?php

namespace App\DTOs\System;

use App\DTOs\BaseDTO;

/**
 * Data transfer object encapsulating system health diagnostics.
 */
class HealthStatusDTO extends BaseDTO
{
    /**
     * Create a new health status DTO.
     *
     * @param  array{status: string, message: string, latency_ms?: ?float}  $database
     * @param  array{status: string, message: string, latency_ms?: ?float}  $redis
     * @param  array{status: string, message: string, driver: string}  $cache
     * @param  array{status: string, message: string, driver: string}  $queue
     */
    public function __construct(
        public readonly string $status,
        public readonly string $environment,
        public readonly string $timestamp,
        public readonly array $database,
        public readonly array $redis,
        public readonly array $cache,
        public readonly array $queue,
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param array{
     *     status: string,
     *     environment: string,
     *     timestamp: string,
     *     database: array{status: string, message: string, latency_ms?: ?float},
     *     redis: array{status: string, message: string, latency_ms?: ?float},
     *     cache: array{status: string, message: string, driver: string},
     *     queue: array{status: string, message: string, driver: string}
     * } $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            status: $data['status'],
            environment: $data['environment'],
            timestamp: $data['timestamp'],
            database: $data['database'],
            redis: $data['redis'],
            cache: $data['cache'],
            queue: $data['queue'],
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array{
     *     status: string,
     *     environment: string,
     *     timestamp: string,
     *     services: array{
     *         database: array{status: string, message: string, latency_ms?: ?float},
     *         redis: array{status: string, message: string, latency_ms?: ?float},
     *         cache: array{status: string, message: string, driver: string},
     *         queue: array{status: string, message: string, driver: string}
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'environment' => $this->environment,
            'timestamp' => $this->timestamp,
            'services' => [
                'database' => $this->database,
                'redis' => $this->redis,
                'cache' => $this->cache,
                'queue' => $this->queue,
            ],
        ];
    }
}
