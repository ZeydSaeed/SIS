<?php

namespace App\Domain\Enrollment\Support;

use App\Domain\Enrollment\Exceptions\MissingEnrollmentIdempotencyKeyException;

final class EnrollmentIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingEnrollmentIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
