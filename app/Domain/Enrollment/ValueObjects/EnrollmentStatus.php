<?php

namespace App\Domain\Enrollment\ValueObjects;

final class EnrollmentStatus
{
    public const ACTIVE = 1;

    public const CANCELLED = 2;

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::CANCELLED,
        ];
    }

    public static function isActive(int $status, ?string $effectiveTo): bool
    {
        return $status === self::ACTIVE && $effectiveTo === null;
    }
}
