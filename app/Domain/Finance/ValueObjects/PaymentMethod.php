<?php

namespace App\Domain\Finance\ValueObjects;

final class PaymentMethod
{
    public const Cash = 1;

    public const BankTransfer = 2;

    public const Card = 3;

    public const Other = 9;

    public static function isValid(int $method): bool
    {
        return in_array($method, [self::Cash, self::BankTransfer, self::Card, self::Other], true);
    }
}
