<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class GetClassStructureQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
