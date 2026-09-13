<?php

namespace App\Application\Enrollment\Queries;

final readonly class GetEnrollmentSubjectQuery
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
    ) {}
}
