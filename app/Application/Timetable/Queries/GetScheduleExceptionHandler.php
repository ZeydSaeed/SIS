<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Timetable\DTOs\ScheduleExceptionDTO;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;

final class GetScheduleExceptionHandler implements QueryHandler
{
    public function __construct(
        private readonly ScheduleExceptionRepositoryInterface $exceptions,
    ) {}

    public function handle(Query $query): ?ScheduleExceptionDTO
    {
        assert($query instanceof GetScheduleExceptionQuery);

        $row = $this->exceptions->findById($query->schoolId, $query->exceptionId);
        if ($row === null) {
            return null;
        }

        return new ScheduleExceptionDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            scheduleId: $row->scheduleId,
            exceptionDate: $row->exceptionDate,
            substituteTeacherId: $row->substituteTeacherId,
            substituteRoomId: $row->substituteRoomId,
            reason: $row->reason,
        );
    }
}
