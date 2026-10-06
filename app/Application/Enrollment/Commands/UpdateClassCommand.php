<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateClassCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $classId,
        public int $gradeLevelId,
        public string $name,
        public ?int $capacity,
        public ?string $idempotencyKey,
    ) {}
}
