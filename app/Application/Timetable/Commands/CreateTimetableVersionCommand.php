<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «حفظ إصدار»: snapshots the working grid as a draft version, with its quality and source fingerprint. */
final readonly class CreateTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $name,
        public ?string $reason,
        public ?int $generationRunId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
