<?php

namespace App\Domain\Promotion\ValueObjects;

final class PromotionStatus
{
    public const Promoted = 1;

    public const Retained = 2;

    public const Conditional = 3;

    public static function isValid(int $status): bool
    {
        return in_array($status, [self::Promoted, self::Retained, self::Conditional], true);
    }
}
