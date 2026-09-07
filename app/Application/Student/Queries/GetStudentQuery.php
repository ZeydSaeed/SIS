<?php

namespace App\Application\Student\Queries;

use App\Application\Contracts\Query;

final readonly class GetStudentQuery implements Query
{
    public function __construct(
        public int $studentId,
        public int $schoolId = 0,
    ) {}
}
