<?php

namespace App\Optimization\Enums;

enum OptimizationMode: string
{
    case Observe = 'observe';
    case Recommend = 'recommend';
    case Autonomous = 'autonomous';

    public function level(): int
    {
        return match ($this) {
            self::Observe => 0,
            self::Recommend => 1,
            self::Autonomous => 2,
        };
    }

    public function allowsCodeChanges(): bool
    {
        return $this === self::Autonomous;
    }

    public function allowsDbAutoExecute(): bool
    {
        return $this !== self::Observe;
    }
}
