<?php

namespace App\Intelligence\Support;

use Illuminate\Support\Str;

class CorrelationContext
{
    private static ?string $correlationId = null;

    public static function id(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = (string) Str::uuid();
        }

        return self::$correlationId;
    }

    public static function set(?string $correlationId): void
    {
        self::$correlationId = $correlationId;
    }

    public static function reset(): void
    {
        self::$correlationId = null;
    }
}
