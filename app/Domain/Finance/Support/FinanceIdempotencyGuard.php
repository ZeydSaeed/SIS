<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Exceptions\MissingFinanceIdempotencyKeyException;

final class FinanceIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingFinanceIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
