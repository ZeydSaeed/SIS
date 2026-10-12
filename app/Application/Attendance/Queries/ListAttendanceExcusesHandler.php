<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class ListAttendanceExcusesHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceWorkspaceReadInterface $read,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof ListAttendanceExcusesQuery);

        $term = trim((string) $query->studentSearch);

        return new AttendanceViewDTO($this->read->excuses($query->schoolId, $query->academicYearId, $query->filters, $query->page, $query->perPage) + [
            'students' => mb_strlen($term) >= 2 ? $this->read->searchEnrollments($query->schoolId, $query->academicYearId, $term, 20) : [],
        ]);
    }
}
