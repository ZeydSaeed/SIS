<?php

namespace App\Domain\Audit\Support;

use App\Domain\Audit\Exceptions\MissingAuditIdempotencyKeyException;

final class AuditIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingAuditIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
