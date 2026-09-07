<?php

namespace App\Optimization\Contracts;

use App\Intelligence\Models\OptimizationEvent;
use App\Intelligence\Models\Recommendation;

interface OptimizationOperation
{
    public function actionType(): string;

    public function isRollbackSupported(): bool;

    public function restoreStrategy(): string;

    /**
     * @return array{executed: bool, event: ?OptimizationEvent, reason: ?string}
     */
    public function apply(Recommendation $recommendation): array;

    public function rollback(Recommendation $recommendation, OptimizationEvent $event): bool;
}
