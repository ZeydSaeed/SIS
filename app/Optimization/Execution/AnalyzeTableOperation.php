<?php

namespace App\Optimization\Execution;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Optimization\Contracts\OptimizationOperation;

final class AnalyzeTableOperation implements OptimizationOperation
{
    public function __construct(
        private readonly SafeAutoExecutor $safeAutoExecutor,
    ) {}

    public function actionType(): string
    {
        return 'analyze';
    }

    public function isRollbackSupported(): bool
    {
        return false;
    }

    public function restoreStrategy(): string
    {
        return 'non_reversible_safe — ANALYZE refreshes planner statistics only';
    }

    public function apply(Recommendation $recommendation): array
    {
        $event = $this->safeAutoExecutor->attempt($recommendation);

        if ($event === null) {
            return [
                'executed' => false,
                'event' => null,
                'reason' => 'Safe auto executor declined — approval or policy gate',
            ];
        }

        return ['executed' => true, 'event' => $event, 'reason' => null];
    }

    public function rollback(Recommendation $recommendation, OptimizationEvent $event): bool
    {
        return false;
    }
}
