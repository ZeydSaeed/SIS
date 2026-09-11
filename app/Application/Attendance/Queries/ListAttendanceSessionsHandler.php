<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\AttendanceSessionListPageDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class ListAttendanceSessionsHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceReadRepositoryInterface $attendance,
    ) {}

    public function handle(Query $query): AttendanceSessionListPageDTO
    {
        assert($query instanceof ListAttendanceSessionsQuery);

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));

        $result = $this->attendance->listSessions(
            $query->schoolId,
            $query->academicYearId,
            $query->sectionId,
            $query->dateFrom,
            $query->dateTo,
            $query->status,
            $page,
            $perPage,
        );

        return new AttendanceSessionListPageDTO($result['items'], $result['pagination']);
    }
}
