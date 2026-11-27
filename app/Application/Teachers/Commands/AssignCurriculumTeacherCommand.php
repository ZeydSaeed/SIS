<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/**
 * «المناهج»: a teacher teaches a subject line of a curriculum — as its main (or only) teacher, or as an assistant.
 * The place (branch, specialization, class) is the curriculum's; the section is the teacher's (null = all sections).
 */
final readonly class AssignCurriculumTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $curriculumSubjectId,
        public int $teacherId,
        public int $role,
        public ?string $idempotencyKey,
        /** The part taught (one of the subject's types: نظري، عملي …); null = the whole subject. */
        public ?int $subjectType = null,
        /** The section taught; null = all sections of the class (or the curriculum's own section). */
        public ?int $sectionId = null,
    ) {}
}
