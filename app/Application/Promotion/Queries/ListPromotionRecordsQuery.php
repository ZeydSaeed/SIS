<?php

namespace App\Application\Promotion\Queries;

final readonly class ListPromotionRecordsQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
