<?php

namespace App\Domain\Academic\Repositories;

use App\Domain\Academic\Data\HolidaySnapshot;

interface HolidayRepositoryInterface
{
    public const ACTIVE = 1;

    public const INACTIVE = 2;

    /**
     * Holidays visible to a school: global (school_id NULL) + school-scoped. Holidays taken out of the calendar
     * (status 2) are listed only when asked for.
     *
     * @return list<HolidaySnapshot>
     */
    public function listVisible(int $schoolId, ?int $academicYearId = null, bool $includeInactive = false): array;

    /** A holiday the school sees (global or its own), or null. */
    public function findVisible(int $schoolId, int $holidayId): ?HolidaySnapshot;

    /** Whether the school already has an active holiday of this name overlapping the dates in the year. */
    public function overlapsActive(int $schoolId, int $academicYearId, string $name, string $startDate, string $endDate, ?int $exceptId): bool;

    public function create(int $schoolId, int $academicYearId, string $name, string $startDate, string $endDate, int $holidayType, string $at): int;

    public function update(int $holidayId, string $name, string $startDate, string $endDate, int $holidayType, string $at): void;

    public function setStatus(int $holidayId, int $status, string $at): void;
}
