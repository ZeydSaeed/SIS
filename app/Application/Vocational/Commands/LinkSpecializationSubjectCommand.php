<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class LinkSpecializationSubjectCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $specializationId,
        public int $subjectId,
        public string $idempotencyKey,
        public bool $isRequired = true,
        public ?int $creditHours = null,
        public ?string $correlationId = null,
    ) {}
}
