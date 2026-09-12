<?php

namespace App\Domain\Timetable\Data;

final readonly class PersistScheduleData
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public int $academicYearId,
        public int $dayOfWeek,
        public int $periodId,
        public int $subjectId,
        public int $teacherId,
        public ?int $roomId,
        public string $at,
        public ?string $correlationId,
        public ?int $createdBy,
    ) {}
}
