<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Timetable\DTOs\ScheduleDTO;
use App\Application\Timetable\DTOs\ScheduleListPageDTO;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;

final class ListSchedulesHandler implements QueryHandler
{
    public function __construct(
        private readonly ScheduleRepositoryInterface $schedules,
    ) {}

    public function handle(Query $query): ScheduleListPageDTO
    {
        assert($query instanceof ListSchedulesQuery);

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));

        $result = $this->schedules->listForSchoolYear(
            $query->schoolId,
            $query->academicYearId,
            $query->sectionId,
            $query->lifecycleStatus,
            $page,
            $perPage,
        );

        $items = [];
        foreach ($result['items'] as $row) {
            $items[] = new ScheduleDTO(
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

        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 0;

        return new ScheduleListPageDTO($items, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => $totalPages,
        ]);
    }
}
