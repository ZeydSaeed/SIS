<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

/** Calculate or finalize the annual result or the year GPA of every active student of a class (or section). */
final readonly class ProcessYearResultsBatchCommand implements Command
{
    public const ANNUAL = 'annual';

    public const GPA = 'gpa';

    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public ?int $sectionId,
        public string $target,
        public string $mode,
        public ?int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
