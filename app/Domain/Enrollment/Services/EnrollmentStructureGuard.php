<?php

namespace App\Domain\Enrollment\Services;

/**
 * Rules for creating / editing classes (الصفوف) and sections (الشعب).
 * Returns the first violated rule as an error code, or null when the input is valid.
 *
 * - Names are required (≤ 100 chars) and unique among the class's year / the section's class.
 * - Capacity is optional; when set it is 1…500 and never below the active enrollments it holds.
 * - A class's grade level changes only while the class has no active enrollments
 *   (the governing curriculum is matched by grade level).
 */
final class EnrollmentStructureGuard
{
    public const MAX_NAME_LENGTH = 100;

    public const MAX_CAPACITY = 500;

    public function nameError(string $name, bool $taken, string $prefix): ?string
    {
        $trimmed = trim($name);
        if ($trimmed === '' || mb_strlen($trimmed) > self::MAX_NAME_LENGTH) {
            return $prefix.'_name_invalid';
        }

        return $taken ? $prefix.'_name_taken' : null;
    }

    public function capacityError(?int $capacity, int $activeEnrollments, string $prefix): ?string
    {
        if ($capacity === null) {
            return null;
        }

        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            return $prefix.'_capacity_invalid';
        }

        return $capacity < $activeEnrollments ? $prefix.'_capacity_below_enrolled' : null;
    }

    public function gradeLevelChangeError(int $currentGradeLevelId, int $newGradeLevelId, int $activeEnrollments): ?string
    {
        if ($currentGradeLevelId === $newGradeLevelId || $activeEnrollments === 0) {
            return null;
        }

        return 'enrollment.class_grade_level_locked';
    }
}
