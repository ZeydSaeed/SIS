<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateScheduleExceptionResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $exceptionId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $exceptionId): self
    {
        return new self(true, $exceptionId);
    }

    public static function fromIdempotency(int $exceptionId): self
    {
        return new self(true, $exceptionId, fromIdempotencyCache: true);
    }
}
