<?php

namespace App\Domain\Results\Support;

use App\Domain\Results\Exceptions\MissingTermResultIdempotencyKeyException;

final class TermResultIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingTermResultIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
