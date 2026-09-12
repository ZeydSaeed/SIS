<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateTeacherCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public string $firstName,
        public string $lastName,
        public ?string $nationalId,
        public ?string $specializationField,
        public ?string $hireDate,
        public ?int $userId,
        public ?string $idempotencyKey,
    ) {}
}
