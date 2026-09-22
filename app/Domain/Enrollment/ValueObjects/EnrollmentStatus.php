<?php

namespace App\Domain\Enrollment\ValueObjects;

final class EnrollmentStatus
{
    public const INACTIVE = 0;

    public const ACTIVE = 1;

    public const CANCELLED = 2;

    public const TRANSFERRED = 3;

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        return [
            self::INACTIVE,
            self::ACTIVE,
            self::CANCELLED,
            self::TRANSFERRED,
        ];
    }

    public static function isActive(int $status, ?string $effectiveTo): bool
    {
        return $status === self::ACTIVE && $effectiveTo === null;
    }

    public static function isReopenable(int $status): bool
    {
        return $status === self::CANCELLED || $status === self::INACTIVE;
    }
}
