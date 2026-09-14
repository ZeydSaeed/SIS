<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\PeriodDTO;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;

final class GetPeriodHandler
{
    public function __construct(
        private readonly PeriodRepositoryInterface $periods,
    ) {}

    public function handle(GetPeriodQuery $query): ?PeriodDTO
    {
        $row = $this->periods->find($query->schoolId, $query->periodId);

        if ($row === null) {
            return null;
        }

        return new PeriodDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            periodNumber: $row->periodNumber,
            startTime: $row->startTime,
            endTime: $row->endTime,
            periodType: $row->periodType,
        );
    }
}
