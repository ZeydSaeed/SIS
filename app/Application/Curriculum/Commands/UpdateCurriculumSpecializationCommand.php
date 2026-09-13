<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateCurriculumSpecializationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
        public ?int $specializationId,
        public ?string $idempotencyKey,
    ) {}
}
