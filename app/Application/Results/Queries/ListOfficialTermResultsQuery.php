<?php

namespace App\Application\Results\Queries;

final readonly class ListOfficialTermResultsQuery
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
    ) {}
}
