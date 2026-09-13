<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class AssignTeacherSchoolCommand implements Command
{
    public function __construct(
        public int $targetSchoolId,
        public int $sourceSchoolId,
        public int $teacherId,
        public int $academicYearId,
        public ?string $idempotencyKey,
    ) {}
}
