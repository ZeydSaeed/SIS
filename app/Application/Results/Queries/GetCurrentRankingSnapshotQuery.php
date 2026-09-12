<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;

final readonly class GetCurrentRankingSnapshotQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
    ) {}
}
