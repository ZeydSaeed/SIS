<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetDailySectionSummaryQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public ?string $date = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}
}
