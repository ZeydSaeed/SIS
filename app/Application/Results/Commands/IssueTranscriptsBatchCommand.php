<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

/** Issue the transcript of every active student of a class (or section) whose current transcript is not up to date. */
final readonly class IssueTranscriptsBatchCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public ?int $sectionId,
        public ?int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
