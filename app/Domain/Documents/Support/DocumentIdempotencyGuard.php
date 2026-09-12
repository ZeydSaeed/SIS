<?php

namespace App\Domain\Documents\Support;

use App\Domain\Documents\Exceptions\MissingDocumentIdempotencyKeyException;

final class DocumentIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingDocumentIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
