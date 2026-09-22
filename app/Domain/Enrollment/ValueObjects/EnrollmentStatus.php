<?php

namespace App\Domain\Enrollment\ValueObjects;

final class EnrollmentStatus
{
    public const INACTIVE = 0;

    public const ACTIVE = 1;

    public const CANCELLED = 2;

    public const TRANSFERRED = 3;

    /** Separated / dismissed from the school placement for the year. */
    public const DISMISSED = 4;

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
            self::DISMISSED,
        ];
    }

    /**
     * Display / progress order: active → inactive → cancelled → dismissed → transferred.
     *
     * @return list<int>
     */
    public static function progressOrder(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::CANCELLED,
            self::DISMISSED,
            self::TRANSFERRED,
        ];
    }

    public static function isActive(int $status, ?string $effectiveTo): bool
    {
        return $status === self::ACTIVE && $effectiveTo === null;
    }

    public static function isClosed(int $status): bool
    {
        return $status === self::INACTIVE
            || $status === self::CANCELLED
            || $status === self::TRANSFERRED
            || $status === self::DISMISSED;
    }

    public static function isReopenable(int $status): bool
    {
        return self::isClosed($status);
    }
}
