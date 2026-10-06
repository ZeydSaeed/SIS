<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class CreateClassCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $gradeLevelId,
        public string $name,
        public ?int $capacity,
        public ?string $idempotencyKey,
    ) {}
}
