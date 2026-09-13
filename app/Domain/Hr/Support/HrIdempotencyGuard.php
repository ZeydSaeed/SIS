<?php

namespace App\Domain\Hr\Support;

use App\Domain\Hr\Exceptions\MissingHrIdempotencyKeyException;

final class HrIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = $key !== null ? trim($key) : '';
        if ($trimmed === '') {
            throw new MissingHrIdempotencyKeyException;
        }

        return $trimmed;
    }
}
