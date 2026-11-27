<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** A new draft version copied from the latest version, when the question has no open version. */
final readonly class ReviseQuestionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $questionId,
        public int $actorId,
        public bool $schoolWide,
        public ?string $idempotencyKey,
    ) {}
}
