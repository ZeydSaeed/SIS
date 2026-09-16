<?php

namespace App\Domain\Student\ValueObjects;

enum StudentReligion: int
{
    case Muslim = 1;
    case Christian = 2;
    case Other = 3;

    public static function default(): self
    {
        return self::Muslim;
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): int => $case->value,
            self::cases(),
        );
    }
}
