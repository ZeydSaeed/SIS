<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class CreateCurriculumCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public string $name,
        public ?int $specializationId,
        public ?string $idempotencyKey,
    ) {}
}
