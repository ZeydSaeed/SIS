<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Timetable\DTOs\ScheduleExceptionDTO;
use App\Application\Timetable\DTOs\ScheduleExceptionListPageDTO;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;

final class ListScheduleExceptionsHandler implements QueryHandler
{
    public function __construct(
        private readonly ScheduleExceptionRepositoryInterface $exceptions,
    ) {}

    public function handle(Query $query): ScheduleExceptionListPageDTO
    {
        assert($query instanceof ListScheduleExceptionsQuery);

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));

        $result = $this->exceptions->listForSchool(
            $query->schoolId,
            $query->scheduleId,
            $query->dateFrom,
            $query->dateTo,
            $page,
            $perPage,
        );

        $items = [];
        foreach ($result['items'] as $row) {
            $items[] = new ScheduleExceptionDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                scheduleId: $row->scheduleId,
                exceptionDate: $row->exceptionDate,
                substituteTeacherId: $row->substituteTeacherId,
                substituteRoomId: $row->substituteRoomId,
                reason: $row->reason,
            );
        }

        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0;

        return new ScheduleExceptionListPageDTO($items, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => $totalPages,
        ]);
    }
}
