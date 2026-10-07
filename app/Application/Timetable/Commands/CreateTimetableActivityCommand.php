<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «نشاط جديد»: what is taught, to which sections / groups, by whom, how long and how often, where. */
final readonly class CreateTimetableActivityCommand implements Command
{
    /**
     * @param  list<array{section_id: int, group_id: int|null}>  $targets
     * @param  list<array{teacher_id: int, role: int, sessions: int|null}>  $teachers
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $subjectId,
        public int $activityType,
        public int $weeklyCount,
        public int $blockLength,
        public ?string $distribution,
        public ?int $roomId,
        public ?int $roomType,
        public ?int $workshopId,
        public int $weekPattern,
        public ?int $termId,
        public ?string $note,
        public array $targets,
        public array $teachers,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}

    /** @return array{subject_id: int, activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, term_id: int|null, note: string|null} */
    public function data(): array
    {
        return [
            'subject_id' => $this->subjectId, 'activity_type' => $this->activityType, 'weekly_count' => $this->weeklyCount,
            'block_length' => $this->blockLength, 'distribution' => $this->distribution, 'room_id' => $this->roomId,
            'room_type' => $this->roomType, 'workshop_id' => $this->workshopId, 'week_pattern' => $this->weekPattern,
            'term_id' => $this->termId, 'note' => $this->note,
        ];
    }
}
