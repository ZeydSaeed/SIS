<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

final readonly class IssueTranscriptCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public string $idempotencyKey,
        public ?string $storageKey = null,
        public ?int $issuedBy = null,
        public ?string $correlationId = null,
    ) {}
}
