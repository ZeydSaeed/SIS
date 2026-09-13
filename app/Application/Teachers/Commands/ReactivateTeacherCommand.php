<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public ?string $idempotencyKey,
    ) {}
}
