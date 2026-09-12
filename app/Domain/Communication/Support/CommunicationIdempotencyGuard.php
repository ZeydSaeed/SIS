<?php

namespace App\Domain\Communication\Support;

use App\Domain\Communication\Exceptions\MissingCommunicationIdempotencyKeyException;

final class CommunicationIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingCommunicationIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
