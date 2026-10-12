<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/**
 * Approve several reviewed sheets. Each item carries the version the reviewer saw, so a sheet that changed
 * after the list was loaded is skipped (reported), never approved blindly.
 */
final readonly class BulkApproveAttendanceSheetsCommand implements Command
{
    public const MAX_ITEMS = 200;

    /**
     * @param  list<array{session_id: int, version: int}>  $items
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $items,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
