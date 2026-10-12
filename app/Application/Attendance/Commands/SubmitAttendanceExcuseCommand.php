<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

final readonly class SubmitAttendanceExcuseCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $enrollmentId,
        public string $dateFrom,
        public string $dateTo,
        public ?int $sessionId,
        public int $excuseType,
        public string $reason,
        public ?int $documentFileId,
        public int $submittedBy,
        public string $idempotencyKey,
        /** Optional attachment: {name, mime, contents} — stored through the Documents module. */
        public ?array $document = null,
    ) {}
}
