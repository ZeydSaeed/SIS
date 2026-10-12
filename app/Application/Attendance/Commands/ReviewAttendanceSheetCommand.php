<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Domain\Attendance\ValueObjects\SheetAction;

/** Administrative review step: start review, approve, return for correction (reason) or reject (reason). */
final readonly class ReviewAttendanceSheetCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $sessionId,
        public int $expectedVersion,
        public SheetAction $action,
        public ?string $reason,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
