<?php

namespace App\Domain\Timetable\Data;

final readonly class ScheduleSnapshot
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
        public ?int $groupId = null,
        public ?int $weekNo = null,
        public ?int $coTeacherId = null,
        public ?int $joinedTo = null,
        public bool $locked = false,
        public ?int $activityId = null,
    ) {}
}
