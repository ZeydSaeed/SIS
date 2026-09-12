<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class AddTeacherQualificationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public int $academicYearId,
        public int $qualificationType,
        public string $title,
        public ?string $institution,
        public ?int $yearObtained,
        public ?string $documentStorageKey,
        public ?string $idempotencyKey,
    ) {}
}
