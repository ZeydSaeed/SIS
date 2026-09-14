<?php

namespace App\Application\Vocational\Queries;

final readonly class GetSpecializationSubjectQuery
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
    ) {}
}
