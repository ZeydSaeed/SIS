<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class VoidTeacherQualificationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $qualificationId,
        public int $academicYearId,
        public ?string $idempotencyKey = null,
    ) {}
}
