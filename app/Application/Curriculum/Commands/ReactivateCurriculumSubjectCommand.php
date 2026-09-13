<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateCurriculumSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $linkId,
        public ?string $idempotencyKey,
    ) {}
}
