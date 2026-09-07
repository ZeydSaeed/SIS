<?php

namespace App\Optimization\Rollback;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;

final class NonReversibleRollbackStrategy implements RollbackStrategy
{
    public function supports(string $actionType): bool
    {
        return $actionType === 'analyze';
    }

    public function isRollbackSupported(): bool
    {
        return false;
    }

    public function restoreStrategy(): string
    {
        return 'non_reversible_safe — ANALYZE refreshes planner statistics only';
    }

    public function rollback(Recommendation $recommendation, ?OptimizationEvent $event, array $checkpoint): array
    {
        return [
            'rolled_back' => false,
            'reason' => 'Operation is non-reversible — status marked only',
        ];
    }

    public function verify(Recommendation $recommendation, array $checkpoint): bool
    {
        return true;
    }
}
