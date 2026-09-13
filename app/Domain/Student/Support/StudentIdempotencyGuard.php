<?php

namespace App\Domain\Student\Support;

use App\Domain\Student\Exceptions\MissingStudentIdempotencyKeyException;

final class StudentIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingStudentIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
