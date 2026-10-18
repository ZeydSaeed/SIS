<?php

namespace App\Domain\Academic\Services;

use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;

/**
 * Rules of «الاجازات». Answers with the first broken rule's error code (Arabic text in the UI), so a database
 * constraint behind it never surfaces as a 500. A school edits only its own holidays — the global ones
 * (school_id NULL, set for every school) are read-only here.
 */
final class HolidayGuard
{
    public const MAX_NAME = 255;

    /** 1 = official (national / religious), 2 = school, 3 = break between terms. */
    public const TYPES = [1, 2, 3];

    public function __construct(
        private readonly HolidayRepositoryInterface $holidays,
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    /** @return string|null error code */
    public function saveRejection(int $schoolId, ?int $holidayId, ?int $academicYearId, string $name, string $startDate, string $endDate, int $holidayType): ?string
    {
        $current = null;
        if ($holidayId !== null) {
            $current = $this->holidays->findVisible($schoolId, $holidayId);
            if ($current === null) {
                return 'academic.holiday_not_found';
            }
            if ($current->schoolId === null) {
                return 'academic.holiday_global_readonly';
            }
            $academicYearId = $current->academicYearId;
        }

        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME) {
            return 'academic.holiday_name_invalid';
        }
        if (! in_array($holidayType, self::TYPES, true)) {
            return 'academic.holiday_type_invalid';
        }
        if (strtotime($startDate) === false || strtotime($endDate) === false || $endDate < $startDate) {
            return 'academic.holiday_dates_invalid';
        }

        $year = $academicYearId === null ? null : $this->years->findById($academicYearId);
        if ($year === null) {
            return 'academic.holiday_year_invalid';
        }
        if (! AcademicYearDateWindow::contains($year->startDate, $year->endDate, $startDate, $endDate)) {
            return 'academic.holiday_outside_year';
        }
        if ($this->holidays->overlapsActive($schoolId, $year->id, $name, $startDate, $endDate, $holidayId)) {
            return 'academic.holiday_duplicate';
        }

        return null;
    }

    /** @return string|null error code */
    public function statusRejection(int $schoolId, int $holidayId, bool $active): ?string
    {
        $holiday = $this->holidays->findVisible($schoolId, $holidayId);
        if ($holiday === null) {
            return 'academic.holiday_not_found';
        }
        if ($holiday->schoolId === null) {
            return 'academic.holiday_global_readonly';
        }
        if ($active && $holiday->status !== HolidayRepositoryInterface::ACTIVE
            && $this->holidays->overlapsActive($schoolId, $holiday->academicYearId, $holiday->name, $holiday->startDate, $holiday->endDate, $holiday->id)) {
            return 'academic.holiday_duplicate';
        }

        return null;
    }
}
