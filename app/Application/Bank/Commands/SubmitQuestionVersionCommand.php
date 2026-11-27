<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** The author sends their draft for review; its content freezes. */
final readonly class SubmitQuestionVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $versionId,
        public int $actorId,
        public bool $schoolWide,
        public ?string $idempotencyKey,
    ) {}
}
