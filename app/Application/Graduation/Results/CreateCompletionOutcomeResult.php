<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateCompletionOutcomeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $completionOutcomeId = null,
        public ?int $schoolId = null,
        public ?int $enrollmentId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $completionOutcomeId, int $schoolId, int $enrollmentId): self
    {
        return new self(true, $completionOutcomeId, $schoolId, $enrollmentId);
    }

    public static function fromIdempotency(int $completionOutcomeId, int $schoolId, int $enrollmentId): self
    {
        return new self(true, $completionOutcomeId, $schoolId, $enrollmentId, fromIdempotencyCache: true);
    }
}
