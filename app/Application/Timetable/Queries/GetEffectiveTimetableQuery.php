<?php

namespace App\Application\Timetable\Queries;

/** The timetable governing a date, for a section or a teacher (Attendance, substitution, «اليوم»). */
final readonly class GetEffectiveTimetableQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $date,
        public ?int $sectionId = null,
        public ?int $teacherId = null,
    ) {}
}
