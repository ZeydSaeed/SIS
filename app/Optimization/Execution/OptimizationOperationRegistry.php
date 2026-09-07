<?php

namespace App\Optimization\Execution;

use App\Optimization\Contracts\OptimizationOperation;

final class OptimizationOperationRegistry
{
    /** @var array<string, OptimizationOperation> */
    private array $operations = [];

    public function register(OptimizationOperation $operation): void
    {
        $this->operations[$operation->actionType()] = $operation;
    }

    public function forAction(string $actionType): ?OptimizationOperation
    {
        return $this->operations[$actionType] ?? null;
    }
}
