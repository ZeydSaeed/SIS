<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class ChangeTeacherEmployeeCodeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public string $employeeCode,
        public ?string $idempotencyKey,
    ) {}
}
