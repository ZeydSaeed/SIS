<?php

namespace App\Application\Finance\Queries;

final readonly class ListStudentFeesQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $enrollmentId = null,
        public ?int $academicYearId = null,
        public ?int $feeStatus = null,
    ) {}
}
