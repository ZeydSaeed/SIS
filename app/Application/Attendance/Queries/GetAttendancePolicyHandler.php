<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\DTOs\AttendanceViewDTO;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetAttendancePolicyHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendancePolicyResolver $policies,
    ) {}

    public function handle(Query $query): AttendanceViewDTO
    {
        assert($query instanceof GetAttendancePolicyQuery);

        $policy = $this->policies->current($query->schoolId, $query->academicYearId);

        return new AttendanceViewDTO([
            'policy' => $policy->toArray(),
            'is_default' => $policy->id === null,
            'default_timezone' => $this->policies->defaultTimezone(),
        ]);
    }
}
