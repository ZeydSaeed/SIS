<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class CreateSectionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $classId,
        public string $name,
        public ?int $capacity,
        /** رائد الصف — an active teacher of the school in the class's academic year. */
        public ?int $homeroomTeacherId,
        public ?string $idempotencyKey,
    ) {}
}
