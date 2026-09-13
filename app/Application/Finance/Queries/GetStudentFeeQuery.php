<?php

namespace App\Application\Finance\Queries;

final readonly class GetStudentFeeQuery
{
    public function __construct(
        public int $schoolId,
        public int $studentFeeId,
    ) {}
}
