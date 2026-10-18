<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;

final readonly class SuggestLeaveSubstitutesQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $scheduleId,
        public string $date,
    ) {}
}
