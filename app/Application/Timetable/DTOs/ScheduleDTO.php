<?php

namespace App\Application\Timetable\DTOs;

final readonly class ScheduleDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $sectionId,
        public int $academicYearId,
        public int $dayOfWeek,
        public int $periodId,
        public int $subjectId,
        public int $teacherId,
        public ?int $roomId,
        public int $lifecycleStatus,
    ) {}
}
