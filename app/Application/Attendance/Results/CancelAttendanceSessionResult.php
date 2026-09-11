<?php

namespace App\Application\Attendance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelAttendanceSessionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $sessionId = null,
        public ?int $previousStatus = null,
        public ?int $newStatus = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $sessionId, int $previousStatus, int $newStatus): self
    {
        return new self(true, $sessionId, $previousStatus, $newStatus);
    }

    public static function fromIdempotency(int $sessionId, int $previousStatus, int $newStatus): self
    {
        return new self(
            true,
            $sessionId,
            $previousStatus,
            $newStatus,
            fromIdempotencyCache: true,
        );
    }
}
