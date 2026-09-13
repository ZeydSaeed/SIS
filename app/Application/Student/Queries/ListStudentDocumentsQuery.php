<?php

namespace App\Application\Student\Queries;

final readonly class ListStudentDocumentsQuery
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
    ) {}
}
