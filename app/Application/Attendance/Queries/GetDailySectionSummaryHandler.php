<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\DailySectionSummaryDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetDailySectionSummaryHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceReadRepositoryInterface $attendance,
    ) {}

    /**
     * @return list<DailySectionSummaryDTO>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof GetDailySectionSummaryQuery);

        return $this->attendance->getDailySectionSummary(
            $query->schoolId,
            $query->sectionId,
            $query->date,
            $query->dateFrom,
            $query->dateTo,
        );
    }
}
