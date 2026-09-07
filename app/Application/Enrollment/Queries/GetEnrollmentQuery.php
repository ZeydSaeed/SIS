<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class GetEnrollmentQuery implements Query
{
    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
    ) {}
}
