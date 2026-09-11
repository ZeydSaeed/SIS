<?php

namespace App\Application\Attendance\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class MarkSectionAttendanceResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $sessionId = null,
        public int $markedCount = 0,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $sessionId, int $markedCount): self
    {
        return new self(true, $sessionId, $markedCount);
    }

    public static function fromIdempotency(int $sessionId, int $markedCount): self
    {
        return new self(true, $sessionId, $markedCount, fromIdempotencyCache: true);
    }
}
