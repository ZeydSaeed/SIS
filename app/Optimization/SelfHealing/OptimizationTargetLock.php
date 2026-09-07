<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\Cache;

final class OptimizationTargetLock
{
    public function acquire(string $target): bool
    {
        $ttl = (int) config('optimization.worker.lock_ttl_seconds', 600);

        return Cache::add($this->lockKey($target), now()->toIso8601String(), $ttl);
    }

    public function release(string $target): void
    {
        Cache::forget($this->lockKey($target));
    }

    public function isLocked(string $target): bool
    {
        return Cache::has($this->lockKey($target));
    }

    private function lockKey(string $target): string
    {
        return 'optimization:target-lock:'.md5($target);
    }
}
