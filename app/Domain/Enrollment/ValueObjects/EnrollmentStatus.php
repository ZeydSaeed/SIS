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
     * Prior placement segment closed because a newer active placement
     * was created for the same student + academic year (mid-year move).
     */
    public const SUPERSEDED = 5;

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
            self::SUPERSEDED,
        ];
    }

    /**
     * Display / progress order: active → inactive → cancelled → dismissed → transferred → superseded.
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
            self::SUPERSEDED,
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
            || $status === self::DISMISSED
            || $status === self::SUPERSEDED;
    }

    public static function isReopenable(int $status): bool
    {
        // Superseded segments are history only — reopen would risk a second active row.
        return self::isClosed($status) && $status !== self::SUPERSEDED;
    }

    /**
     * Statuses operators may set via bulk/status actions (not system history).
     *
     * @return list<int>
     */
    public static function operatorAssignable(): array
    {
        return [
            self::INACTIVE,
            self::ACTIVE,
            self::CANCELLED,
            self::TRANSFERRED,
            self::DISMISSED,
        ];
    }
}
