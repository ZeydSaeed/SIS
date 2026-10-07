<?php

namespace App\Domain\Timetable\Data;

/**
 * A lesson to write. The engine fields default to «whole section, every week, one teacher, placed by hand».
 */
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
        public ?int $groupId = null,
        public ?int $weekNo = null,
        public ?int $coTeacherId = null,
        public ?int $activityId = null,
    ) {}
}
