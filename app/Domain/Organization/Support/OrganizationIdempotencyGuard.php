<?php

namespace App\Domain\Organization\Support;

use App\Domain\Organization\Exceptions\MissingOrganizationIdempotencyKeyException;

final class OrganizationIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingOrganizationIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
