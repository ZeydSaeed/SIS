<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class DecideAttendanceExcuseCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $excuseId,
        public int $expectedVersion,
        public bool $approve,
        public ?string $note,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
