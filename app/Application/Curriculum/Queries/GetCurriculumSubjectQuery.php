<?php

namespace App\Application\Curriculum\Queries;

final readonly class GetCurriculumSubjectQuery
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
    ) {}
}
