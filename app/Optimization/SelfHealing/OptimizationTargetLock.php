<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\Cache;

final class OptimizationTargetLock
{
    public function __construct(
        private readonly TargetNormalizer $normalizer,
    ) {}

    public function acquire(string $target, ?string $schemaName = null, ?string $tableName = null): bool
    {
        $ttl = (int) config('optimization.worker.lock_ttl_seconds', 600);

        return Cache::add(
            $this->normalizer->lockKey($target, $schemaName, $tableName),
            now()->toIso8601String(),
            $ttl,
        );
    }

    public function release(string $target, ?string $schemaName = null, ?string $tableName = null): void
    {
        Cache::forget($this->normalizer->lockKey($target, $schemaName, $tableName));
    }

    public function isLocked(string $target, ?string $schemaName = null, ?string $tableName = null): bool
    {
        return Cache::has($this->normalizer->lockKey($target, $schemaName, $tableName));
    }

    public function normalize(string $target, ?string $schemaName = null, ?string $tableName = null): string
    {
        return $this->normalizer->normalize($target, $schemaName, $tableName);
    }
}
