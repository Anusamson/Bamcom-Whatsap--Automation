<?php

namespace App\Services;

use App\Services\Contracts\ServiceInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base service class providing transaction safety and structured logging.
 */
abstract class BaseService implements ServiceInterface
{
    /**
     * Execute a database callback inside a database transaction.
     *
     * @template TReturn
     *
     * @param  (Closure(): TReturn)  $callback
     * @return TReturn
     *
     * @throws Throwable
     */
    protected function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    /**
     * Log an informational message with standard service context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info(sprintf('[%s] %s', static::class, $message), $context);
    }

    /**
     * Log an error message with standard service context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = [], ?Throwable $exception = null): void
    {
        if ($exception !== null) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        Log::error(sprintf('[%s] %s', static::class, $message), $context);
    }
}
