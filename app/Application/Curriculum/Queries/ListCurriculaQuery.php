<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListCurriculaQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
