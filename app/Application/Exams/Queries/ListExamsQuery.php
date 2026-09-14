<?php

namespace App\Application\Exams\Queries;

final readonly class ListExamsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId = null,
        public ?int $status = null,
    ) {}
}
