<?php

namespace App\Domain\Timetable\Solver;

/**
 * What a solver found.
 *
 * - placements: card id → [day, start index, facility key|null];
 * - unplaced: card id → diagnosis {reasons: code → count of rejected positions, suggestions: list of
 *   single-blocker fixes (what alone stands in the way of some position)};
 * - violations: per compiled rule (type, id, source, hard, count) — the relaxed / broken rules;
 * - hardViolations / softPenalty: totals for the final state; stopped: cancelled or out of time.
 */
final readonly class SolverResult
{
    /**
     * @param  array<string, array{0: int, 1: int, 2: string|null}>  $placements
     * @param  array<string, array{reasons: array<string, int>, suggestions: list<array<string, mixed>>}>  $unplaced
     * @param  list<array{rule_type: string, rule_id: int|null, source: string, hard: bool, count: int, priority: int}>  $violations
     */
    public function __construct(
        public array $placements,
        public array $unplaced,
        public array $violations,
        public int $hardViolations,
        public int $softPenalty,
        public int $iterations,
        public int $elapsedMs,
        public bool $stopped,
    ) {}

    public function feasible(): bool
    {
        return $this->unplaced === [] && $this->hardViolations === 0;
    }
}
