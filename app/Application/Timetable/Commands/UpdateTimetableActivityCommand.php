<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * Changes an activity's load, block, distribution, room needs, week pattern or note. Its targets and teachers
 * do not change in place — end the activity and create another (history stays readable).
 */
final readonly class UpdateTimetableActivityCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $activityId,
        public int $activityType,
        public int $weeklyCount,
        public int $blockLength,
        public ?string $distribution,
        public ?int $roomId,
        public ?int $roomType,
        public ?int $workshopId,
        public int $weekPattern,
        public ?string $note,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}

    /** @return array{activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, note: string|null} */
    public function data(): array
    {
        return [
            'activity_type' => $this->activityType, 'weekly_count' => $this->weeklyCount, 'block_length' => $this->blockLength,
            'distribution' => $this->distribution, 'room_id' => $this->roomId, 'room_type' => $this->roomType,
            'workshop_id' => $this->workshopId, 'week_pattern' => $this->weekPattern, 'note' => $this->note,
        ];
    }
}
