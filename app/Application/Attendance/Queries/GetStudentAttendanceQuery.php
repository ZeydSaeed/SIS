<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetStudentAttendanceQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $academicYearId,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
