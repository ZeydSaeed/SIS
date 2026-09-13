<?php

namespace App\Application\Curriculum\Queries;

final readonly class GetSubjectQuery
{
    public function __construct(
        public int $subjectId,
    ) {}
}
