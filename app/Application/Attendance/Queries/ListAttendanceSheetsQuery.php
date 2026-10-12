<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

/** The administrative review board («مراجعة الكشوف»), server-filtered and paginated. */
final readonly class ListAttendanceSheetsQuery implements Query
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $filters,
        public int $page,
        public int $perPage,
    ) {}
}
