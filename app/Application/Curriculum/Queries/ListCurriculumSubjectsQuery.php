<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListCurriculumSubjectsQuery
{
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
    ) {}
}
