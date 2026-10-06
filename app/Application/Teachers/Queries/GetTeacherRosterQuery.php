<?php

namespace App\Application\Teachers\Queries;

use App\Application\Contracts\Query;

final readonly class GetTeacherRosterQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
