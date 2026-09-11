<?php

namespace App\Application\Attendance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CorrectAttendanceRecordResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $recordId = null,
        public ?int $previousStatus = null,
        public ?int $newStatus = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $recordId, int $previousStatus, int $newStatus): self
    {
        return new self(true, $recordId, $previousStatus, $newStatus);
    }

    public static function fromIdempotency(int $recordId, int $previousStatus, int $newStatus): self
    {
        return new self(true, $recordId, $previousStatus, $newStatus, fromIdempotencyCache: true);
    }
}
