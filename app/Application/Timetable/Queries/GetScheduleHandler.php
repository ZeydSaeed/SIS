<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Timetable\DTOs\ScheduleDTO;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;

final class GetScheduleHandler implements QueryHandler
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
    ) {}

    public function handle(Query $query): ?ScheduleDTO
    {
        assert($query instanceof GetScheduleQuery);

        $row = $this->schedules->findById($query->schoolId, $query->scheduleId);
        if ($row === null) {
            return null;
        }

        return new ScheduleDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            sectionId: $row->sectionId,
            academicYearId: $row->academicYearId,
            dayOfWeek: $row->dayOfWeek,
            periodId: $row->periodId,
            subjectId: $row->subjectId,
            teacherId: $row->teacherId,
            roomId: $row->roomId,
            lifecycleStatus: $row->lifecycleStatus,
        );
    }
}
