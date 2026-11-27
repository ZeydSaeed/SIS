<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** An approver (never the author) approves or rejects a version under review. */
final readonly class DecideQuestionVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public string $decision,
        public string $note,
        public int $actorId,
        public ?string $idempotencyKey,
    ) {}
}
