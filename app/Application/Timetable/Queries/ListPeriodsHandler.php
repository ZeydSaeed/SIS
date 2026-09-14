<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\PeriodDTO;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;

final class ListPeriodsHandler
{
    public function __construct(
        private readonly PeriodRepositoryInterface $periods,
    ) {}

    /** @return list<PeriodDTO> */
    public function handle(ListPeriodsQuery $query): array
    {
        return array_map(
            static fn ($row): PeriodDTO => new PeriodDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                periodNumber: $row->periodNumber,
                startTime: $row->startTime,
                endTime: $row->endTime,
                periodType: $row->periodType,
            ),
            $this->periods->listForSchool($query->schoolId),
        );
    }
}
