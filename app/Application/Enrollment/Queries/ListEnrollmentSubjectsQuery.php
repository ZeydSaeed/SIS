<?php

namespace App\Application\Enrollment\Queries;

final readonly class ListEnrollmentSubjectsQuery
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
    ) {}
}
