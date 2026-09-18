<?php

namespace App\Application\Admission\Queries;

use App\Application\Contracts\Query;

final readonly class GetAdmissionWorkspaceQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
