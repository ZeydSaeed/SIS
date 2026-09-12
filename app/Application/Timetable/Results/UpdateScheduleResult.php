<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateScheduleResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $scheduleId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $scheduleId): self
    {
        return new self(true, $scheduleId);
    }

    public static function fromIdempotency(int $scheduleId): self
    {
        return new self(true, $scheduleId, fromIdempotencyCache: true);
    }
}
