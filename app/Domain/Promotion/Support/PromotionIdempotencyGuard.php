<?php

namespace App\Domain\Promotion\Support;

use App\Domain\Promotion\Exceptions\MissingPromotionIdempotencyKeyException;

final class PromotionIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingPromotionIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
