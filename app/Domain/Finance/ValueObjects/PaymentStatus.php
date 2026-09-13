<?php

namespace App\Domain\Finance\ValueObjects;

final class PaymentStatus
{
    public const Posted = 1;

    public const Voided = 2;

    public static function isPosted(int $status): bool
    {
        return $status === self::Posted;
    }
}
