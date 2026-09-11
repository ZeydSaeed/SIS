<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class CreateAttendanceSessionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $sectionId,
        public int $subjectId,
        public string $sessionDate,
        public int $teacherId,
        public ?int $periodId = null,
        public ?int $createdBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
