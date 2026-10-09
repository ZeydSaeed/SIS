<?php

namespace App\Domain\Timetable\Solver;

use App\Domain\Timetable\ValueObjects\ConstraintPriority;
use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * How hard a generation run searches («تعقيد الإنشاء») and how strictly it treats the rules («مستوى القيود»).
 *
 * Complexity changes the search, not a label:
 * - normal  one attempt, short budget, light ejection chains — fast, for small schools;
 * - large   two seeded attempts, deeper ejection, more improvement iterations;
 * - huge    three seeded attempts, the deepest ejection and the largest iteration cap (big / complex institutions).
 * The best attempt wins: fewest hard violations, then fewest unplaced cards, then the lowest soft penalty.
 *
 * Constraint level (hard ≠ soft is always known: {@see GenerationMode::isHard()}):
 * - basic    only the structural hard rules and the hard user rules are kept — soft preferences are skipped
 *            for a quick first timetable;
 * - relaxed  only CRITICAL rules are hard; the rest are optimised as penalties and reported when broken;
 * - strict   rules down to HIGH are hard; a card that cannot comply stays unplaced.
 */
final readonly class GenerationStrategy
{
    public const NORMAL = 'normal';

    public const LARGE = 'large';

    public const HUGE = 'huge';

    public const BASIC = 'basic';

    public const RELAXED = 'relaxed';

    public const STRICT = 'strict';

    public const COMPLEXITIES = [self::NORMAL, self::LARGE, self::HUGE];

    public const LEVELS = [self::BASIC, self::RELAXED, self::STRICT];

    /** complexity → [default budget s, iteration cap, ejection attempts, attempts] */
    private const PRESETS = [
        self::NORMAL => [10.0, 60_000, 30, 1],
        self::LARGE => [40.0, 250_000, 60, 2],
        self::HUGE => [110.0, 800_000, 120, 3],
    ];

    private function __construct(
        public string $complexity,
        public ?string $constraintLevel,
        private float $budget,
    ) {}

    /** @param  array<string, mixed>  $options  run options: complexity, constraint_level, time_budget */
    public static function from(array $options): self
    {
        $complexity = in_array($options['complexity'] ?? null, self::COMPLEXITIES, true) ? (string) $options['complexity'] : self::NORMAL;
        $level = in_array($options['constraint_level'] ?? null, self::LEVELS, true) ? (string) $options['constraint_level'] : null;
        $budget = isset($options['time_budget']) ? (float) $options['time_budget'] : self::PRESETS[$complexity][0];

        return new self($complexity, $level, max(2.0, min(120.0, $budget)));
    }

    /** The mode a fresh generation runs in for the chosen level (optimize / repair keep the requested mode). */
    public function mode(GenerationMode $requested): GenerationMode
    {
        if ($requested->keepsCurrentGrid() || $this->constraintLevel === null) {
            return $requested;
        }

        return match ($this->constraintLevel) {
            self::STRICT => GenerationMode::Strict,
            self::RELAXED => GenerationMode::Relaxed,
            default => GenerationMode::Balanced,
        };
    }

    /**
     * The user rules the run keeps: all of them, except at the basic level where soft rules are skipped.
     *
     * @param  list<array{id: int, rule_type: string, priority: int, scope: array<string, int|null>, params: array<string, mixed>}>  $rules
     * @return list<array<string, mixed>>
     */
    public function rules(array $rules, GenerationMode $mode): array
    {
        if ($this->constraintLevel !== self::BASIC) {
            return $rules;
        }

        return array_values(array_filter($rules, static fn (array $r): bool => $mode->isHard(ConstraintPriority::tryFrom((int) $r['priority']) ?? ConstraintPriority::Low)));
    }

    /**
     * One {@see SolverOptions} per attempt: seeds seed, seed+1 …, the budget shared between attempts.
     *
     * @return list<SolverOptions>
     */
    public function attempts(int $seed): array
    {
        [, $iterations, $ejection, $count] = self::PRESETS[$this->complexity];
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = new SolverOptions(seed: $seed + $i, timeBudgetSeconds: $this->budget / $count, maxIterations: $iterations, ejectionAttempts: $ejection);
        }

        return $out;
    }

    public function budget(): float
    {
        return $this->budget;
    }

    /** True when `$candidate` beats `$best`. */
    public static function better(SolverResult $candidate, ?SolverResult $best): bool
    {
        return $best === null
            || [$candidate->hardViolations, count($candidate->unplaced), $candidate->softPenalty] < [$best->hardViolations, count($best->unplaced), $best->softPenalty];
    }
}
