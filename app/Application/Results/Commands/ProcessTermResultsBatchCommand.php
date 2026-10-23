<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

/**
 * Calculate (operational) or finalize (official) the term result of one subject for every seated student of a class
 * (or section) — one Calculate/FinalizeTermResult command per student.
 */
final readonly class ProcessTermResultsBatchCommand implements Command
{
    public const CALCULATE = 'calculate';

    public const FINALIZE = 'finalize';

    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public int $classId,
        public ?int $sectionId,
        public string $mode,
        public ?int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
