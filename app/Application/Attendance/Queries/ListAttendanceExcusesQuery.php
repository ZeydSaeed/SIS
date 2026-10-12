<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class ListAttendanceExcusesQuery implements Query
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
        public ?string $studentSearch = null,
    ) {}
}
