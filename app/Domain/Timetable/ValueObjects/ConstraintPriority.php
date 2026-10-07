<?php

namespace App\Domain\Timetable\ValueObjects;

/**
 * How much a constraint matters. CRITICAL is always hard; the generation mode decides how far down the
 * scale rules become hard ({@see GenerationMode::hardFrom()}). The rest are soft, weighted by priority.
 */
enum ConstraintPriority: int
{
    case Critical = 1;
    case VeryHigh = 2;
    case High = 3;
    case Medium = 4;
    case Low = 5;
    case VeryLow = 6;

    public function defaultWeight(): int
    {
        return match ($this) {
            self::Critical => 1_000_000,
            self::VeryHigh => 100_000,
            self::High => 10_000,
            self::Medium => 1_000,
            self::Low => 100,
            self::VeryLow => 10,
        };
    }
}
