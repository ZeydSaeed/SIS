<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class ListEnrollmentPlacementHistoryQuery implements Query
{
    /**
     * @param list<int> $studentIds
     */
    public function __construct(
        public int $schoolId,
        public array $studentIds,
        public ?int $academicYearId = null,
    ) {}
}
