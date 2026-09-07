<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

final readonly class ListEnrollmentsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
