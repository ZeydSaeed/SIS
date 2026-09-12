<?php

namespace App\Domain\Teachers\Support;

use App\Domain\Teachers\Exceptions\MissingTeacherIdempotencyKeyException;

final class TeacherIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingTeacherIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
