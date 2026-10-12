<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

/**
 * Role dashboards. The school view reads the current school; the central view compares the schools the user is
 * authorised for (each read under that school's own RLS scope).
 */
final readonly class GetAttendanceDashboardQuery implements Query
{
    /**
     * @param  list<int>  $centralSchoolIds  authorised schools for the central comparison (empty = school view only)
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $centralSchoolIds = [],
    ) {}
}
