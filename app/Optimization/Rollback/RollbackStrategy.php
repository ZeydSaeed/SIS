<?php

namespace App\Optimization\Rollback;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;

interface RollbackStrategy
{
    public function supports(string $actionType): bool;

    public function isRollbackSupported(): bool;

    public function restoreStrategy(): string;

    /**
     * @param  array<string, mixed>  $checkpoint
     * @return array{rolled_back: bool, reason: ?string}
     */
    public function rollback(Recommendation $recommendation, ?OptimizationEvent $event, array $checkpoint): array;

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    public function verify(Recommendation $recommendation, array $checkpoint): bool;
}
