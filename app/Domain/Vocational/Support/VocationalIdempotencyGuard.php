<?php

namespace App\Domain\Vocational\Support;

use App\Domain\Vocational\Exceptions\MissingVocationalIdempotencyKeyException;

final class VocationalIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingVocationalIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
