<?php

namespace App\Application\Teachers\Queries;

final readonly class ListTeachersQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
