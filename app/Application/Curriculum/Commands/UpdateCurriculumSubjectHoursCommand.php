<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

/** Change «نظري» / «عملي» hours of a subject already in a curriculum (weekly hours = their sum). */
final readonly class UpdateCurriculumSubjectHoursCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
        public ?int $theoryHours,
        public ?int $practicalHours,
        public ?string $idempotencyKey,
    ) {}
}
