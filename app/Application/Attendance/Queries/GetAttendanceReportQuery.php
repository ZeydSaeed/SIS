<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetAttendanceReportQuery implements Query
{
    /** Hard cap of an export; larger extracts must be narrowed by filters. */
    public const EXPORT_CAP = 20000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $type,
        public array $filters,
        public int $page,
        public int $perPage,
        public bool $export = false,
    ) {}
}
