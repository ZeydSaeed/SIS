<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Contracts\AttendanceReadRepositoryInterface;
use App\Application\Attendance\DTOs\SectionAttendanceDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetSectionAttendanceHandler implements QueryHandler
{
    public function __construct(
        private readonly AttendanceReadRepositoryInterface $attendance,
    ) {}

    public function handle(Query $query): SectionAttendanceDTO
    {
        assert($query instanceof GetSectionAttendanceQuery);

        $dto = $this->attendance->getSectionAttendance(
            $query->schoolId,
            $query->sectionId,
            $query->date,
            $query->academicYearId,
        );

        return $dto ?? new SectionAttendanceDTO(
            schoolId: $query->schoolId,
            sectionId: $query->sectionId,
            date: $query->date,
            academicYearId: $query->academicYearId,
            sessions: [],
            records: [],
        );
    }
}
