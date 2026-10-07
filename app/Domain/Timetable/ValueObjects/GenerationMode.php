<?php

namespace App\Domain\Timetable\ValueObjects;

/**
 * Generation modes (spec §31).
 *
 * Strict    — rules down to HIGH are hard; cards that cannot comply stay unplaced.
 * Balanced  — rules down to VERY_HIGH are hard; the rest are optimised as penalties.
 * Relaxed   — only CRITICAL is hard; every relaxed HIGH+ rule is reported.
 * Optimize  — starts from the current grid, never removes a lesson, only moves to lower penalty.
 * Repair    — starts from the current grid, re-places only lessons in conflict or missing.
 * (What-if and partial regeneration are options of a run, not modes: any mode on a copy / a scope.)
 */
enum GenerationMode: int
{
    case Strict = 1;
    case Balanced = 2;
    case Relaxed = 3;
    case Optimize = 4;
    case Repair = 5;

    /** The lowest priority (highest value) still treated as hard in this mode. */
    public function hardFrom(): ConstraintPriority
    {
        return match ($this) {
            self::Strict => ConstraintPriority::High,
            self::Relaxed => ConstraintPriority::Critical,
            default => ConstraintPriority::VeryHigh,
        };
    }

    public function isHard(ConstraintPriority $priority): bool
    {
        return $priority->value <= $this->hardFrom()->value;
    }

    /** Starts from the lessons already on the grid. */
    public function keepsCurrentGrid(): bool
    {
        return $this === self::Optimize || $this === self::Repair;
    }
}
