<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeCache
{
    /**
     * Read through cache, falling back to the source callback when cache fails.
     *
     * @param  array<string, mixed>  $context
     */
    public function remember(string $key, mixed $ttl, Closure $callback, array $context = []): mixed
    {
        $missing = new \stdClass;

        try {
            $cached = Cache::get($key, $missing);
        } catch (Throwable $exception) {
            $this->logCacheFailure('Cache read failed; using source fallback.', $exception, $context);

            return $callback();
        }

        if ($cached !== $missing) {
            return $cached;
        }

        $value = $callback();

        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable $exception) {
            $this->logCacheFailure('Cache write failed after source read.', $exception, $context);
        }

        return $value;
    }

    /**
     * Return a cached value, or the supplied default when cache is unavailable.
     *
     * @param  array<string, mixed>  $context
     */
    public function get(string $key, mixed $default = null, array $context = []): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable $exception) {
            $this->logCacheFailure('Cache read failed; using default fallback.', $exception, $context);

            return value($default);
        }
    }

    /**
     * Store a value permanently, logging cache write failures without failing the caller.
     *
     * @param  array<string, mixed>  $context
     */
    public function forever(string $key, mixed $value, array $context = []): bool
    {
        try {
            return Cache::forever($key, $value);
        } catch (Throwable $exception) {
            $this->logCacheFailure('Cache write failed.', $exception, $context);

            return false;
        }
    }

    /**
     * Clear a cache key, logging cache invalidation failures without failing the caller.
     *
     * @param  array<string, mixed>  $context
     */
    public function forget(string $key, array $context = []): bool
    {
        try {
            return Cache::forget($key);
        } catch (Throwable $exception) {
            $this->logCacheFailure('Cache invalidation failed.', $exception, $context);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logCacheFailure(string $message, Throwable $exception, array $context): void
    {
        Log::warning($message, [
            ...$context,
            'failure_type' => $exception::class,
        ]);
    }
}
