<?php

namespace App\Application\Finance\Queries;

final readonly class ListFinanceTransactionsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $studentId = null,
        public ?int $academicYearId = null,
    ) {}
}
