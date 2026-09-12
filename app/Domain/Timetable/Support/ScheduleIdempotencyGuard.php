<?php

namespace App\Domain\Timetable\Support;

use App\Domain\Timetable\Exceptions\MissingScheduleIdempotencyKeyException;

final class ScheduleIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingScheduleIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
