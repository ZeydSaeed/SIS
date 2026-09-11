<?php

namespace App\Application\Attendance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CloseAttendanceSessionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $sessionId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $sessionId): self
    {
        return new self(true, $sessionId);
    }

    public static function fromIdempotency(int $sessionId): self
    {
        return new self(true, $sessionId, fromIdempotencyCache: true);
    }
}
