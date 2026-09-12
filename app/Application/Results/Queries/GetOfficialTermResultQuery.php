<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;

final readonly class GetOfficialTermResultQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
    ) {}
}
