<?php

namespace App\Domain\Transfers\Support;

use App\Domain\Transfers\Exceptions\MissingTransferIdempotencyKeyException;

final class TransferIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingTransferIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
