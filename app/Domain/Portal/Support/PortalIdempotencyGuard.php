<?php

namespace App\Domain\Portal\Support;

use App\Domain\Portal\Exceptions\MissingPortalIdempotencyKeyException;

final class PortalIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingPortalIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
