<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\StudentAttendanceListPageDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetStudentAttendanceHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceReadRepositoryInterface $attendance,
    ) {}

    public function handle(Query $query): StudentAttendanceListPageDTO
    {
        assert($query instanceof GetStudentAttendanceQuery);

        $page = max(1, $query->page);
        $perPage = max(1, min(100, $query->perPage));

        $result = $this->attendance->getStudentAttendance(
            $query->schoolId,
            $query->studentId,
            $query->academicYearId,
            $query->dateFrom,
            $query->dateTo,
            $page,
            $perPage,
        );

        return new StudentAttendanceListPageDTO($result['items'], $result['pagination']);
    }
}
