<?php

namespace Tests\Support\Optimization;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Contracts\OptimizationOperation;

final class TestAnalyzeOperation implements OptimizationOperation
{
    /**
     * @param  array<string, mixed>  $applyResult
     */
    public function __construct(
        private array $applyResult = ['executed' => true, 'event' => null, 'reason' => null],
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
        return 'non_reversible_safe';
    }

    public function apply(Recommendation $recommendation): array
    {
        return $this->applyResult;
    }

    public function rollback(Recommendation $recommendation, OptimizationEvent $event): bool
    {
        return false;
    }
}
