<?php

namespace App\Domain\Timetable\Solver;

/**
 * How long and how reproducibly to search. The same problem, options and seed give the same result
 * (the time budget only cuts the improvement phase short; `maxIterations` makes runs fully repeatable).
 */
final readonly class SolverOptions
{
    public function __construct(
        public int $seed = 1,
        public float $timeBudgetSeconds = 10.0,
        public int $maxIterations = 20_000,
        public int $ejectionAttempts = 40,
    ) {}
}
