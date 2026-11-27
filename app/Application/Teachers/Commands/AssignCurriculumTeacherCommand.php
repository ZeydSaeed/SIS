<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/**
 * «المناهج»: a teacher teaches a subject line of a curriculum — as its main (or only) teacher, or as an assistant.
 * The place (branch … section) is the curriculum's. `replaceMain` ends the current main teacher in the same step.
 */
final readonly class AssignCurriculumTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $curriculumSubjectId,
        public int $teacherId,
        public int $role,
        public bool $replaceMain,
        public ?string $idempotencyKey,
    ) {}
}
