<?php

namespace App\Application\Exams\Queries;

/** Grade-entry sheet: the sessions the actor may grade in a year and, when one is chosen, its gradable seats. */
final readonly class GetGradeEntrySheetQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $examSessionId,
        public int $actorUserId,
        public bool $schoolWide,
    ) {}
}
