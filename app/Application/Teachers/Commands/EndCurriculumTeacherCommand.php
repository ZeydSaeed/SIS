<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** «المناهج»: the teacher no longer teaches this curriculum subject line (status 2 + effective_to; history stays). */
final readonly class EndCurriculumTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $curriculumSubjectId,
        public int $assignmentId,
    ) {}
}
