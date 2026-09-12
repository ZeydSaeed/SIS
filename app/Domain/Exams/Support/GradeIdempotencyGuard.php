<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Exceptions\MissingGradeIdempotencyKeyException;

/**
 * Phase 7.3-U01 — HD-7.3-001 = A / P7-D5: grade mutating commands require non-empty idempotency keys.
 */
final class GradeIdempotencyGuard
{
    public static function requireKey(?string $idempotencyKey): string
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw MissingGradeIdempotencyKeyException::required();
        }

        return $idempotencyKey;
    }
}
