<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class LinkCurriculumSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
        public int $subjectId,
        public ?int $weeklyHours,
        public bool $isRequired,
        public int $subjectOrder,
        public ?string $idempotencyKey,
        /** «نظري» / «عملي» split; when either is given weekly hours become their sum. */
        public ?int $theoryHours = null,
        public ?int $practicalHours = null,
    ) {}
}
