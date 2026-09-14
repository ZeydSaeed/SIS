<?php

namespace App\Domain\Academic\Repositories;

use App\Domain\Academic\Data\HolidaySnapshot;

interface HolidayRepositoryInterface
{
    /**
     * Holidays visible to a school: global (school_id NULL) + school-scoped.
     *
     * @return list<HolidaySnapshot>
     */
    public function listVisible(int $schoolId, ?int $academicYearId = null): array;
}
