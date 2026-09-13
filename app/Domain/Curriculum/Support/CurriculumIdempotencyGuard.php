<?php

namespace App\Domain\Curriculum\Support;

use App\Domain\Curriculum\Exceptions\MissingCurriculumIdempotencyKeyException;

final class CurriculumIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingCurriculumIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
