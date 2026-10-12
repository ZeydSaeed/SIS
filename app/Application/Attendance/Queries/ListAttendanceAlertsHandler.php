<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Attendance\Services\AttendanceAlertRules;

final class ListAttendanceAlertsHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceWorkspaceReadInterface $read,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof ListAttendanceAlertsQuery);

        return new AttendanceViewDTO($this->read->alerts($query->schoolId, $query->academicYearId, $query->filters, $query->page, $query->perPage) + [
            'rules' => AttendanceAlertRules::ALL,
        ]);
    }
}
