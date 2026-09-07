<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelEnrollmentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $enrollmentId = null,
        public ?string $effectiveTo = null,
        public ?int $status = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $enrollmentId, string $effectiveTo, int $status): self
    {
        return new self(true, $enrollmentId, $effectiveTo, $status);
    }

    public static function fromIdempotency(int $enrollmentId, string $effectiveTo, int $status): self
    {
        return new self(true, $enrollmentId, $effectiveTo, $status, fromIdempotencyCache: true);
    }
}
