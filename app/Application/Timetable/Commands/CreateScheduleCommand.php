<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class CreateScheduleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public int $academicYearId,
        public int $dayOfWeek,
        public int $periodId,
        public int $subjectId,
        public int $teacherId,
        public string $idempotencyKey,
        public ?int $roomId = null,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
